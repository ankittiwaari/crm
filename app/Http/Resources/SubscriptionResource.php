<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name),
            'product_unit_label' => $this->whenLoaded('product', fn () => $this->product->unit_label),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_live' => $this->status->isLive(),
            'billing_cycle' => $this->billing_cycle->value,
            'billing_cycle_label' => $this->billing_cycle->label(),
            'billing_cycle_short' => $this->billing_cycle->shortLabel(),
            'pricing_model' => $this->pricing_model->value,
            'rate' => $this->rate,
            'rate_rupees' => Money::toRupees($this->rate),
            'quantity' => $this->quantity,
            'unit_count' => $this->unit_count,
            'amount_per_cycle' => $this->amountPerCycle(),
            'annual_value' => $this->annualValue(),
            'setup_fee' => $this->setup_fee,
            'setup_fee_rupees' => $this->setup_fee === null ? null : Money::toRupees($this->setup_fee),
            'is_promotional' => $this->is_promotional,
            'promo_ends_on' => $this->promo_ends_on?->toDateString(),
            'promo_note' => $this->promo_note,
            'start_date' => $this->start_date->toDateString(),
            'current_period_start' => $this->current_period_start?->toDateString(),
            'current_period_end' => $this->current_period_end?->toDateString(),
            'days_until_renewal' => $this->daysUntilRenewal(),
            'auto_renew' => $this->auto_renew,
            'renewal_notice_days' => $this->renewal_notice_days,
            'grace_days' => $this->grace_days,
            'notes' => $this->notes,
            'rate_revisions' => $this->whenLoaded(
                'rateRevisions',
                fn () => RateRevisionResource::collection($this->rateRevisions)->resolve($request),
            ),
        ];
    }
}
