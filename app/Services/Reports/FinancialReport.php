<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PricingModel;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\FinancialYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Figures for one financial year: revenue is the taxable value of issued invoices (by invoice date),
 * collections are payments plus TDS (by payment date) and expenses exclude claimable GST.
 */
class FinancialReport
{
    /**
     * Ageing buckets in days since the invoice date. 45 days is the MSME payment limit.
     */
    public const array AGEING_BUCKETS = [
        ['label' => '0–30 days', 'from' => 0, 'to' => 30],
        ['label' => '31–45 days', 'from' => 31, 'to' => 45],
        ['label' => '46–90 days', 'from' => 46, 'to' => 90],
        ['label' => '90+ days', 'from' => 91, 'to' => null],
    ];

    /**
     * @return array<string, mixed>
     */
    public function forYear(FinancialYear $year): array
    {
        $invoices = $this->issuedInvoices($year)->get();
        $payments = Payment::query()->whereBetween('paid_on', [$year->start(), $year->end()])->with('invoice:id,client_id,client_snapshot')->get();
        $expenses = Expense::query()->whereBetween('spent_on', [$year->start(), $year->end()])->get();

        return [
            'totals' => [
                'revenue' => (int) $invoices->sum('taxable_total'),
                'gst' => $invoices->sum(fn (Invoice $invoice): int => $invoice->cgst_total + $invoice->sgst_total + $invoice->igst_total),
                'invoiced' => (int) $invoices->sum('total'),
                'collected' => $payments->sum(fn (Payment $payment): int => $payment->settles()),
                'tds' => (int) $payments->sum('tds_amount'),
                'expenses' => $expenses->sum(fn (Expense $expense): int => $expense->netCost()),
                'input_gst' => (int) $expenses->sum('gst_amount'),
                'profit' => (int) $invoices->sum('taxable_total') - $expenses->sum(fn (Expense $expense): int => $expense->netCost()),
                'invoice_count' => $invoices->count(),
            ],
            'months' => $this->months($year, $invoices, $payments, $expenses),
            'byProduct' => $this->byProduct($year),
            'tdsByClient' => $payments
                ->filter(fn (Payment $payment): bool => $payment->tds_amount > 0)
                ->groupBy(fn (Payment $payment): int => $payment->invoice->client_id)
                ->map(fn (Collection $items): array => [
                    'client_name' => $items->first()->invoice->client_snapshot['name'] ?? '',
                    'tds' => (int) $items->sum('tds_amount'),
                    'payments' => $items->count(),
                ])
                ->sortByDesc('tds')
                ->values()
                ->all(),
        ];
    }

    /**
     * Unpaid invoices grouped by age, as of today, whatever the year.
     *
     * @return array{total: int, buckets: list<array{label: string, amount: int, count: int}>, over_msme_limit: list<array<string, mixed>>}
     */
    public function receivablesAgeing(): array
    {
        $open = Invoice::query()->open()->orderBy('issue_date')->get();
        $age = fn (Invoice $invoice): int => (int) $invoice->issue_date->diffInDays(today());

        $buckets = array_map(function (array $bucket) use ($open, $age): array {
            $matching = $open->filter(fn (Invoice $invoice): bool => $age($invoice) >= $bucket['from']
                && ($bucket['to'] === null || $age($invoice) <= $bucket['to']));

            return [
                'label' => $bucket['label'],
                'amount' => $matching->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
                'count' => $matching->count(),
            ];
        }, self::AGEING_BUCKETS);

        return [
            'total' => $open->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
            'buckets' => $buckets,
            'over_msme_limit' => array_values($open
                ->filter(fn (Invoice $invoice): bool => $age($invoice) > 45)
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'client_name' => $invoice->client_snapshot['name'] ?? '',
                    'balance_due' => $invoice->balanceDue(),
                    'days' => $age($invoice),
                ])
                ->all()),
        ];
    }

    /**
     * How each product is priced across live subscriptions, to help set standard prices later.
     *
     * @return list<array<string, mixed>>
     */
    public function pricing(): array
    {
        return array_values(Product::query()
            ->with(['subscriptions' => fn ($query) => $query->live()])
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product): bool => $product->subscriptions->isNotEmpty())
            ->map(function (Product $product): array {
                $subscriptions = $product->subscriptions;
                $withUnits = $subscriptions->filter(fn (Subscription $subscription): bool => $this->units($subscription) > 0);
                $annual = $subscriptions->map(fn (Subscription $subscription): int => $subscription->annualValue())->filter();

                return [
                    'product' => $product->name,
                    'unit_label' => $product->unit_label,
                    'subscriptions' => $subscriptions->count(),
                    'promotional' => $subscriptions->where('is_promotional', true)->count(),
                    'per_unit' => $subscriptions->where('pricing_model', PricingModel::PerUnit)->count(),
                    'annual_total' => (int) $annual->sum(),
                    'annual_average' => $annual->isEmpty() ? null : (int) round($annual->avg()),
                    'annual_min' => $annual->min(),
                    'annual_max' => $annual->max(),
                    'units_recorded' => $withUnits->sum(fn (Subscription $subscription): int => $this->units($subscription)),
                    'per_unit_yearly' => $withUnits->isEmpty() ? null : (int) round(
                        $withUnits->sum(fn (Subscription $subscription): int => $subscription->annualValue())
                        / $withUnits->sum(fn (Subscription $subscription): int => $this->units($subscription)),
                    ),
                ];
            })
            ->all());
    }

    /**
     * Issued invoices (not drafts or cancelled) dated within the year.
     *
     * @return Builder<Invoice>
     */
    private function issuedInvoices(FinancialYear $year): Builder
    {
        return Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereBetween('issue_date', [$year->start(), $year->end()]);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Expense>  $expenses
     * @return list<array{month: string, label: string, revenue: int, collected: int, expenses: int}>
     */
    private function months(FinancialYear $year, Collection $invoices, Collection $payments, Collection $expenses): array
    {
        return array_map(fn ($month): array => [
            'month' => $month->format('Y-m'),
            'label' => $month->format('M'),
            'revenue' => (int) $invoices->filter(fn (Invoice $invoice): bool => $invoice->issue_date->isSameMonth($month))->sum('taxable_total'),
            'collected' => $payments->filter(fn (Payment $payment): bool => $payment->paid_on->isSameMonth($month))->sum(fn (Payment $payment): int => $payment->settles()),
            'expenses' => $expenses->filter(fn (Expense $expense): bool => $expense->spent_on->isSameMonth($month))->sum(fn (Expense $expense): int => $expense->netCost()),
        ], $year->months());
    }

    /**
     * @return list<array{product: string, revenue: int, share: float}>
     */
    private function byProduct(FinancialYear $year): array
    {
        $items = InvoiceItem::query()
            ->whereHas('invoice', fn (Builder $query) => $query
                ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
                ->whereBetween('issue_date', [$year->start(), $year->end()]))
            ->get(['product_id', 'taxable_amount']);

        $names = Product::withTrashed()->pluck('name', 'id');
        $total = max(1, (int) $items->sum('taxable_amount'));

        return array_values($items
            ->groupBy(fn (InvoiceItem $item): string => $item->product_id !== null ? (string) ($names[$item->product_id] ?? 'Other') : 'Custom lines')
            ->map(fn (Collection $group, string $name): array => [
                'product' => $name,
                'revenue' => (int) $group->sum('taxable_amount'),
                'share' => round($group->sum('taxable_amount') / $total * 100, 1),
            ])
            ->sortByDesc('revenue')
            ->all());
    }

    private function units(Subscription $subscription): int
    {
        return $subscription->pricing_model === PricingModel::PerUnit
            ? $subscription->quantity
            : (int) $subscription->unit_count;
    }
}
