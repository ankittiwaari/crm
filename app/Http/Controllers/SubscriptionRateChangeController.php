<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\ChangeSubscriptionRate;
use App\Http\Requests\Subscriptions\RateChangeRequest;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SubscriptionRateChangeController extends Controller
{
    public function store(RateChangeRequest $request, Subscription $subscription, ChangeSubscriptionRate $changeRate): RedirectResponse
    {
        $changeRate->handle($subscription, $request->rateChange(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate updated and saved to the history.')]);

        return to_route('clients.show', $subscription->client_id);
    }
}
