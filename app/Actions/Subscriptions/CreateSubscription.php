<?php

namespace App\Actions\Subscriptions;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateSubscription
{
    public function __construct(private SetCurrentPeriod $period) {}

    /**
     * Add a product to a client at the agreed rate and record it as the first entry in the rate history.
     * A lead that starts using a product becomes an active client.
     *
     * @param  array<string, mixed>  $data  Validated input, amounts in paise.
     */
    public function handle(Client $client, array $data, ?User $user = null): Subscription
    {
        return DB::transaction(function () use ($client, $data, $user): Subscription {
            $subscription = $client->subscriptions()->make($data);
            $this->period->apply($subscription, $data['current_period_end'] ?? null);
            $subscription->save();

            $subscription->rateRevisions()->create([
                'user_id' => $user?->id,
                'new_pricing_model' => $subscription->pricing_model,
                'new_rate' => $subscription->rate,
                'new_quantity' => $subscription->quantity,
                'effective_from' => $subscription->start_date,
                'reason' => 'Initial deal',
            ]);

            if ($client->status === ClientStatus::Lead && $subscription->status->isLive()) {
                $client->update(['status' => ClientStatus::Active]);
            }

            return $subscription;
        });
    }
}
