<?php

namespace App\Actions\Subscriptions;

use App\Enums\PricingModel;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

class SetCurrentPeriod
{
    /**
     * Set the current billing period. A given end date (e.g. when entering an existing client
     * mid-period) wins; otherwise the period containing today is worked out from the start date.
     * Flat deals always have a quantity of 1.
     */
    public function apply(Subscription $subscription, ?string $currentPeriodEnd = null): void
    {
        if ($subscription->pricing_model === PricingModel::Flat) {
            $subscription->quantity = 1;
        }

        $cycle = $subscription->billing_cycle;

        if ($cycle->months() === null) {
            $subscription->current_period_start = null;
            $subscription->current_period_end = null;

            return;
        }

        if ($currentPeriodEnd !== null) {
            $end = CarbonImmutable::parse($currentPeriodEnd);
            $subscription->current_period_end = $end;
            $subscription->current_period_start = $cycle->periodStartFor($end);

            return;
        }

        [$start, $end] = $cycle->periodContaining($subscription->start_date, CarbonImmutable::today());
        $subscription->current_period_start = $start;
        $subscription->current_period_end = $end;
    }
}
