<?php

namespace App\Services\Reports;

use App\Enums\IndianState;
use App\Enums\InvoiceStatus;
use App\Enums\TaxType;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Spreadsheets for your CA and GST returns. GSTR-1 files follow the sections of the GST offline tool
 * (B2B, B2CL, B2CS, HSN); check them against the current portal format before uploading.
 */
class CsvExports
{
    /**
     * Inter-state B2C invoices above this value (₹1 lakh) are reported invoice by invoice (B2CL).
     */
    public const int B2CL_LIMIT = 10_000_000;

    public const array TYPES = [
        'invoices' => 'Invoice register',
        'gstr1-b2b' => 'GSTR-1 B2B',
        'gstr1-b2cl' => 'GSTR-1 B2C large',
        'gstr1-b2cs' => 'GSTR-1 B2C small',
        'hsn-summary' => 'HSN/SAC summary',
        'payments' => 'Payments and TDS',
        'expenses' => 'Expenses',
    ];

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    public function build(string $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($type) {
            'invoices' => $this->invoices($from, $to),
            'gstr1-b2b' => $this->b2b($from, $to),
            'gstr1-b2cl' => $this->b2cl($from, $to),
            'gstr1-b2cs' => $this->b2cs($from, $to),
            'hsn-summary' => $this->hsn($from, $to),
            'payments' => $this->payments($from, $to),
            'expenses' => $this->expenses($from, $to),
            default => throw new InvalidArgumentException("Unknown export [{$type}]."),
        };
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function invoices(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = array_values(Invoice::query()
            ->where('status', '!=', InvoiceStatus::Draft)
            ->whereBetween('issue_date', [$from, $to])
            ->orderBy('financial_year')
            ->orderBy('sequence')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                $invoice->number ?? '',
                $invoice->issue_date->format('d-m-Y'),
                $invoice->due_date->format('d-m-Y'),
                $invoice->status->label(),
                $invoice->client_snapshot['name'] ?? '',
                $invoice->client_snapshot['gstin'] ?? '',
                $this->isB2b($invoice) ? 'B2B' : 'B2C',
                $this->placeOfSupply($invoice),
                $this->rupees($invoice->taxable_total),
                $this->rupees($invoice->cgst_total),
                $this->rupees($invoice->sgst_total),
                $this->rupees($invoice->igst_total),
                $this->rupees($invoice->round_off),
                $this->rupees($invoice->total),
                $this->rupees($invoice->amount_settled),
                $this->rupees($invoice->status === InvoiceStatus::Cancelled ? 0 : $invoice->balanceDue()),
                $invoice->cancellation_reason ?? '',
            ])
            ->all());

        return [[
            'Invoice number', 'Invoice date', 'Due date', 'Status', 'Client', 'Client GSTIN', 'Type', 'Place of supply',
            'Taxable value', 'CGST', 'SGST', 'IGST', 'Round off', 'Invoice total', 'Received (incl. TDS)', 'Balance due', 'Cancellation reason',
        ], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function b2b(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];

        foreach ($this->reportable($from, $to)->filter(fn (Invoice $invoice): bool => $this->isB2b($invoice)) as $invoice) {
            foreach ($this->byRate($invoice) as $rate => $taxable) {
                $rows[] = [
                    $invoice->client_snapshot['gstin'] ?? '',
                    $invoice->client_snapshot['name'] ?? '',
                    $invoice->number ?? '',
                    $invoice->issue_date->format('d-M-Y'),
                    $this->rupees($invoice->total),
                    $this->placeOfSupply($invoice),
                    'N',
                    '',
                    'Regular B2B',
                    '',
                    $rate,
                    $this->rupees($taxable),
                    '0.00',
                ];
            }
        }

        return [[
            'GSTIN/UIN of Recipient', 'Receiver Name', 'Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply',
            'Reverse Charge', 'Applicable % of Tax Rate', 'Invoice Type', 'E-Commerce GSTIN', 'Rate', 'Taxable Value', 'Cess Amount',
        ], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function b2cl(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];

        foreach ($this->reportable($from, $to)->filter(fn (Invoice $invoice): bool => $this->isB2cl($invoice)) as $invoice) {
            foreach ($this->byRate($invoice) as $rate => $taxable) {
                $rows[] = [
                    $invoice->number ?? '',
                    $invoice->issue_date->format('d-M-Y'),
                    $this->rupees($invoice->total),
                    $this->placeOfSupply($invoice),
                    '',
                    $rate,
                    $this->rupees($taxable),
                    '0.00',
                    '',
                ];
            }
        }

        return [[
            'Invoice Number', 'Invoice date', 'Invoice Value', 'Place Of Supply', 'Applicable % of Tax Rate',
            'Rate', 'Taxable Value', 'Cess Amount', 'E-Commerce GSTIN',
        ], $rows];
    }

    /**
     * B2C invoices not reported individually, summed by place of supply and rate.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function b2cs(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = [];

        foreach ($this->reportable($from, $to)->filter(fn (Invoice $invoice): bool => ! $this->isB2b($invoice) && ! $this->isB2cl($invoice)) as $invoice) {
            foreach ($this->byRate($invoice) as $rate => $taxable) {
                $key = $this->placeOfSupply($invoice).'|'.$rate;
                $totals[$key] = ($totals[$key] ?? 0) + $taxable;
            }
        }

        ksort($totals);

        $rows = [];

        foreach ($totals as $key => $taxable) {
            [$placeOfSupply, $rate] = explode('|', $key);
            $rows[] = ['OE', $placeOfSupply, '', $rate, $this->rupees($taxable), '0.00', ''];
        }

        return [['Type', 'Place Of Supply', 'Applicable % of Tax Rate', 'Rate', 'Taxable Value', 'Cess Amount', 'E-Commerce GSTIN'], $rows];
    }

    /**
     * Totals by SAC code and rate, split into B2B and B2C supplies.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function hsn(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $groups = [];

        foreach ($this->reportable($from, $to) as $invoice) {
            $supply = $this->isB2b($invoice) ? 'B2B' : 'B2C';

            foreach ($invoice->items as $item) {
                $key = implode('|', [$supply, $item->sac_code, $this->rate($item)]);
                $group = $groups[$key] ?? ['description' => $item->description, 'quantity' => 0, 'value' => 0, 'taxable' => 0, 'igst' => 0, 'cgst' => 0, 'sgst' => 0];

                $groups[$key] = [
                    'description' => $group['description'],
                    'quantity' => $group['quantity'] + $item->quantity,
                    'value' => $group['value'] + $item->line_total,
                    'taxable' => $group['taxable'] + $item->taxable_amount,
                    'igst' => $group['igst'] + $item->igst,
                    'cgst' => $group['cgst'] + $item->cgst,
                    'sgst' => $group['sgst'] + $item->sgst,
                ];
            }
        }

        ksort($groups);

        $rows = [];

        foreach ($groups as $key => $group) {
            [$supply, $sac, $rate] = explode('|', $key);
            $rows[] = [
                $supply, $sac, $group['description'], 'NA', $group['quantity'], $this->rupees($group['value']), $rate,
                $this->rupees($group['taxable']), $this->rupees($group['igst']), $this->rupees($group['cgst']),
                $this->rupees($group['sgst']), '0.00',
            ];
        }

        return [[
            'Supply type', 'HSN/SAC', 'Description', 'UQC', 'Total Quantity', 'Total Value', 'Rate', 'Taxable Value',
            'Integrated Tax Amount', 'Central Tax Amount', 'State/UT Tax Amount', 'Cess Amount',
        ], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function payments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = array_values(Payment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->with('invoice')
            ->orderBy('paid_on')
            ->get()
            ->map(fn (Payment $payment): array => [
                $payment->paid_on->format('d-m-Y'),
                $payment->invoice->number ?? '',
                $payment->invoice->client_snapshot['name'] ?? '',
                $payment->invoice->client_snapshot['gstin'] ?? '',
                $this->rupees($payment->amount),
                $this->rupees($payment->tds_amount),
                $this->rupees($payment->settles()),
                $payment->method->label(),
                $payment->reference ?? '',
            ])
            ->all());

        return [['Date', 'Invoice', 'Client', 'Client GSTIN', 'Amount received', 'TDS deducted', 'Total settled', 'Method', 'Reference'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function expenses(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = array_values(Expense::query()
            ->whereBetween('spent_on', [$from, $to])
            ->orderBy('spent_on')
            ->get()
            ->map(fn (Expense $expense): array => [
                $expense->spent_on->format('d-m-Y'),
                $expense->category->label(),
                $expense->vendor ?? '',
                $expense->description,
                $this->rupees($expense->amount),
                $this->rupees($expense->gst_amount),
                $this->rupees($expense->netCost()),
                $expense->payment_method?->label() ?? '',
                $expense->reference ?? '',
                $expense->receipt_path !== null ? 'Yes' : 'No',
            ])
            ->all());

        return [['Date', 'Category', 'Vendor', 'Description', 'Amount paid', 'Input GST', 'Net cost', 'Method', 'Reference', 'Receipt'], $rows];
    }

    /**
     * Issued, non-cancelled invoices with their lines, for GST returns.
     *
     * @return Collection<int, Invoice>
     */
    private function reportable(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->whereBetween('issue_date', [$from, $to])
            ->with('items')
            ->orderBy('financial_year')
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Taxable value by GST rate.
     *
     * @return array<string, int>
     */
    private function byRate(Invoice $invoice): array
    {
        $rates = [];

        foreach ($invoice->items as $item) {
            $rate = $this->rate($item);
            $rates[$rate] = ($rates[$rate] ?? 0) + $item->taxable_amount;
        }

        ksort($rates, SORT_NUMERIC);

        return $rates;
    }

    private function rate(InvoiceItem $item): string
    {
        return rtrim(rtrim($item->tax_rate, '0'), '.');
    }

    private function isB2b(Invoice $invoice): bool
    {
        return filled($invoice->client_snapshot['gstin'] ?? null);
    }

    private function isB2cl(Invoice $invoice): bool
    {
        return ! $this->isB2b($invoice)
            && $invoice->tax_type === TaxType::InterState
            && $invoice->total > self::B2CL_LIMIT;
    }

    private function placeOfSupply(Invoice $invoice): string
    {
        $code = (string) $invoice->place_of_supply;
        $state = IndianState::tryFrom($code);

        return $state ? "{$code}-{$state->label()}" : $code;
    }

    private function rupees(int $paise): string
    {
        return ($paise < 0 ? '-' : '').Money::toRupees(abs($paise));
    }
}
