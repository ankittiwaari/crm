<?php

namespace App\Http\Controllers;

use App\Actions\Renewals\RenewalLedger;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionRenewalController extends Controller
{
    /**
     * Renew the next period manually (override), with a reason.
     */
    public function store(Request $request, Subscription $subscription, RenewalLedger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'waive' => ['boolean'],
        ], [
            'reason.required' => 'Say why you are renewing without payment, e.g. "Cheque received, not yet cleared".',
        ]);

        $period = $ledger->override($subscription, $validated['reason'], (bool) ($validated['waive'] ?? false), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Renewed until :date.', ['date' => $period->period_end->format('d M Y')])]);

        return to_route('clients.show', $subscription->client_id);
    }

    /**
     * Undo a manual renewal.
     */
    public function destroy(SubscriptionPeriod $period, RenewalLedger $ledger): RedirectResponse
    {
        $ledger->revert($period);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Manual renewal undone.')]);

        return to_route('clients.show', $period->subscription->client_id);
    }
}
