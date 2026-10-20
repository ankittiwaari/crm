<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Proposes invoice lines from a client's subscriptions: the next period when the renewal is due
 * (within the reminder window), otherwise the current period, plus any setup fee not yet billed.
 * Periods already on a non-cancelled invoice are skipped.
 */
class InvoiceSuggestions
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forClient(Client $client, ?int $onlySubscriptionId = null): array
    {
        $subscriptions = $client->subscriptions()
            ->live()
            ->with('product')
            ->when($onlySubscriptionId, fn ($query) => $query->whereKey($onlySubscriptionId))
            ->get();

        $suggestions = [];

        foreach ($subscriptions as $subscription) {
            if ($line = $this->periodLine($subscription)) {
                $suggestions[] = $line;
            }

            if ($subscription->setup_fee && ! $this->hasBeenInvoiced($subscription)) {
                $suggestions[] = $this->line($subscription, [
                    'key' => "setup-{$subscription->id}",
                    'description' => "{$subscription->product->name}, setup and training (one-time)",
                    'quantity' => 1,
                    'unit_price' => $subscription->setup_fee,
                    'period_start' => null,
                    'period_end' => null,
                ]);
            }
        }

        return $suggestions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function periodLine(Subscription $subscription): ?array
    {
        $cycle = $subscription->billing_cycle;

        if ($cycle->months() === null) {
            return $this->hasBeenInvoiced($subscription) ? null : $this->line($subscription, [
                'key' => "subscription-{$subscription->id}",
                'description' => $subscription->product->name,
                'period_start' => null,
                'period_end' => null,
            ]);
        }

        $start = $subscription->current_period_start;
        $end = $subscription->current_period_end;

        if ($start === null || $end === null) {
            return null;
        }

        $renewalDue = $subscription->daysUntilRenewal() <= $subscription->renewal_notice_days;

        if ($renewalDue || $this->periodInvoiced($subscription, $start)) {
            $start = $end->addDay();
            $end = $cycle->periodEnd($start);
        }

        if ($end === null || $this->periodInvoiced($subscription, $start)) {
            return null;
        }

        return $this->line($subscription, [
            'key' => "subscription-{$subscription->id}-{$start->toDateString()}",
            'description' => "{$subscription->product->name}, {$cycle->label()} subscription",
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'is_renewal' => $renewalDue,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function line(Subscription $subscription, array $overrides): array
    {
        return [
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->product_id,
            'sac_code' => $subscription->product->sac_code,
            'quantity' => $subscription->quantity,
            'unit_price' => $subscription->rate,
            'discount' => 0,
            'tax_rate' => $subscription->product->tax_rate,
            'is_renewal' => false,
            ...$overrides,
        ];
    }

    private function periodInvoiced(Subscription $subscription, CarbonImmutable $periodStart): bool
    {
        return $this->billedLines($subscription)->whereDate('period_start', $periodStart)->exists();
    }

    private function hasBeenInvoiced(Subscription $subscription): bool
    {
        return $this->billedLines($subscription)->exists();
    }

    /**
     * @return Builder<InvoiceItem>
     */
    private function billedLines(Subscription $subscription): Builder
    {
        return InvoiceItem::query()
            ->where('subscription_id', $subscription->id)
            ->whereHas('invoice', fn (Builder $query) => $query->where('status', '!=', InvoiceStatus::Cancelled));
    }
}
