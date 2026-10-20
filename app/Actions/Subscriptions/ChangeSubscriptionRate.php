<?php

namespace App\Actions\Subscriptions;

use App\Enums\PricingModel;
use App\Models\RateRevision;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeSubscriptionRate
{
    /**
     * Change the negotiated rate and keep the old one in the rate history.
     *
     * @param  array{pricing_model: PricingModel|string, rate: int, quantity?: int|null, effective_from: string, reason: string}  $data
     */
    public function handle(Subscription $subscription, array $data, ?User $user = null): RateRevision
    {
        $pricingModel = $data['pricing_model'] instanceof PricingModel
            ? $data['pricing_model']
            : PricingModel::from($data['pricing_model']);
        $quantity = $pricingModel === PricingModel::Flat ? 1 : (int) ($data['quantity'] ?? 1);

        if ($pricingModel === $subscription->pricing_model
            && $data['rate'] === $subscription->rate
            && $quantity === $subscription->quantity) {
            throw ValidationException::withMessages([
                'rate' => 'This is the same as the current rate. Change the rate, quantity or pricing to record a revision.',
            ]);
        }

        return DB::transaction(function () use ($subscription, $data, $pricingModel, $quantity, $user): RateRevision {
            $revision = $subscription->rateRevisions()->create([
                'user_id' => $user?->id,
                'old_pricing_model' => $subscription->pricing_model,
                'new_pricing_model' => $pricingModel,
                'old_rate' => $subscription->rate,
                'new_rate' => $data['rate'],
                'old_quantity' => $subscription->quantity,
                'new_quantity' => $quantity,
                'effective_from' => $data['effective_from'],
                'reason' => $data['reason'],
            ]);

            $subscription->update([
                'pricing_model' => $pricingModel,
                'rate' => $data['rate'],
                'quantity' => $quantity,
            ]);

            return $revision;
        });
    }
}
