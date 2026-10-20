<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How far ahead the dashboard looks for renewals and ending promos.
     */
    private const int LOOKAHEAD_DAYS = 30;

    /**
     * Show the "Today" dashboard.
     */
    public function __invoke(): Response
    {
        $profile = CompanyProfile::current();
        $subscriptions = Subscription::query()
            ->live()
            ->whereHas('client')
            ->with(['client:id,name', 'product:id,name'])
            ->get();
        $horizon = today()->addDays(self::LOOKAHEAD_DAYS);
        $openInvoices = Invoice::query()->open()->with('client:id,name')->orderBy('due_date')->get();
        $overdue = $openInvoices->filter(fn (Invoice $invoice): bool => $invoice->isOverdue());
        $renewals = $subscriptions->filter(fn (Subscription $subscription): bool => $subscription->current_period_end !== null
            && $subscription->current_period_end->betweenIncluded(today(), $horizon));

        return Inertia::render('dashboard', [
            'businessProfile' => [
                'name' => $profile->displayName(),
                'gstin' => $profile->gstin,
                'state' => $profile->state()?->label(),
                'udyam_number' => $profile->udyam_number,
                'msme_category' => $profile->msme_category?->label(),
                'missing' => $profile->missingInvoiceDetails(),
            ],
            'setup' => [
                'has_products' => Product::query()->exists(),
                'has_clients' => Client::query()->exists(),
            ],
            'metrics' => [
                'annual_recurring' => $subscriptions->sum(fn (Subscription $subscription): int => $subscription->annualValue()),
                'live_subscriptions' => $subscriptions->count(),
                'renewals_count' => $renewals->count(),
                'renewals_value' => $renewals->sum(fn (Subscription $subscription): int => $subscription->amountPerCycle()),
                'outstanding' => $openInvoices->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
                'outstanding_count' => $openInvoices->count(),
                'overdue_count' => $overdue->count(),
            ],
            'overdue' => $overdue->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client_snapshot['name'] ?? $invoice->client->name,
                'balance_due' => $invoice->balanceDue(),
                'due_date' => $invoice->due_date->toDateString(),
                'days_overdue' => $invoice->daysOverdue(),
            ])->values(),
            'upcoming' => $this->upcoming($subscriptions, $horizon),
            'lookaheadDays' => self::LOOKAHEAD_DAYS,
        ]);
    }

    /**
     * Renewals and promo end dates in the lookahead window, soonest first.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     * @return list<array<string, mixed>>
     */
    private function upcoming(Collection $subscriptions, CarbonImmutable $horizon): array
    {
        $items = [];

        foreach ($subscriptions as $subscription) {
            $item = [
                'subscription_id' => $subscription->id,
                'client_id' => $subscription->client_id,
                'client_name' => $subscription->client->name,
                'product_name' => $subscription->product->name,
                'amount' => $subscription->amountPerCycle(),
                'billing_cycle_short' => $subscription->billing_cycle->shortLabel(),
                'is_promotional' => $subscription->is_promotional,
            ];

            if ($subscription->current_period_end?->betweenIncluded(today(), $horizon)) {
                $items[] = [...$item, 'kind' => 'renewal', 'date' => $subscription->current_period_end->toDateString()];
            }

            if ($subscription->is_promotional && $subscription->promo_ends_on?->betweenIncluded(today(), $horizon)) {
                $items[] = [...$item, 'kind' => 'promo_ends', 'date' => $subscription->promo_ends_on->toDateString()];
            }
        }

        usort($items, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $items;
    }
}
