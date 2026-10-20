<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Renewals\RenewalLedger;
use App\Actions\Subscriptions\ChangeSubscriptionRate;
use App\Actions\Subscriptions\CreateSubscription;
use App\Actions\Subscriptions\SetCurrentPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\RateChangeRequest;
use App\Http\Requests\Subscriptions\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Client;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class SubscriptionController extends Controller
{
    public function store(SubscriptionRequest $request, Client $client, CreateSubscription $createSubscription): SubscriptionResource
    {
        $subscription = $createSubscription->handle($client, $request->subscriptionAttributes(), $request->user());

        return $this->resource($subscription);
    }

    /**
     * Update the terms. The rate changes through rate-changes so it is kept in the history.
     */
    public function update(SubscriptionRequest $request, Subscription $subscription, SetCurrentPeriod $period): SubscriptionResource
    {
        $data = $request->subscriptionAttributes();
        $subscription->fill(Arr::except($data, ['current_period_end']));

        if ($subscription->isDirty(['billing_cycle', 'start_date']) || filled($data['current_period_end'] ?? null)) {
            $period->apply($subscription, $data['current_period_end'] ?? null);
        }

        $subscription->save();

        return $this->resource($subscription);
    }

    public function destroy(Subscription $subscription): Response
    {
        $subscription->delete();

        return response()->noContent();
    }

    public function changeRate(RateChangeRequest $request, Subscription $subscription, ChangeSubscriptionRate $changeRate): SubscriptionResource
    {
        $changeRate->handle($subscription, $request->rateChange(), $request->user());

        return $this->resource($subscription->refresh());
    }

    /**
     * Renew the next period without full payment (override), or as a free extension with waive=true.
     */
    public function renew(Request $request, Subscription $subscription, RenewalLedger $ledger): SubscriptionResource
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'waive' => ['boolean'],
        ]);

        $ledger->override($subscription, $validated['reason'], (bool) ($validated['waive'] ?? false), $request->user());

        return $this->resource($subscription->refresh());
    }

    public function undoRenewal(SubscriptionPeriod $period, RenewalLedger $ledger): SubscriptionResource
    {
        $ledger->revert($period);

        return $this->resource($period->subscription->refresh());
    }

    private function resource(Subscription $subscription): SubscriptionResource
    {
        return SubscriptionResource::make($subscription->load([
            'product',
            'rateRevisions' => fn ($revisions) => $revisions->latest('effective_from')->latest('id'),
            'periods' => fn ($periods) => $periods->latest('period_start')->limit(6),
        ]));
    }
}
