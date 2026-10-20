<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\CreateSubscription;
use App\Actions\Subscriptions\SetCurrentPeriod;
use App\Http\Requests\Subscriptions\SubscriptionRequest;
use App\Models\Client;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function store(SubscriptionRequest $request, Client $client, CreateSubscription $createSubscription): RedirectResponse
    {
        $subscription = $createSubscription->handle($client, $request->subscriptionAttributes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':product added.', [
            'product' => $subscription->product->name,
        ])]);

        return to_route('clients.show', $client);
    }

    /**
     * Update the terms of a subscription. The rate is changed separately so it is recorded.
     */
    public function update(SubscriptionRequest $request, Subscription $subscription, SetCurrentPeriod $period): RedirectResponse
    {
        $data = $request->subscriptionAttributes();

        $subscription->fill(Arr::except($data, ['current_period_end']));

        if ($subscription->isDirty(['billing_cycle', 'start_date']) || filled($data['current_period_end'] ?? null)) {
            $period->apply($subscription, $data['current_period_end'] ?? null);
        }

        $subscription->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription updated.')]);

        return to_route('clients.show', $subscription->client_id);
    }

    /**
     * Remove a subscription added by mistake. Use the "Cancelled" status for real cancellations.
     */
    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription removed.')]);

        return to_route('clients.show', $subscription->client_id);
    }
}
