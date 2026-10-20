<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
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
            'renewal_state' => $this->renewalState(),
            'grace_ends_on' => $this->graceEndsOn()?->toDateString(),
            'next_period' => ($next = $this->nextPeriod()) ? ['start' => $next[0]->toDateString(), 'end' => $next[1]->toDateString()] : null,
            'periods' => $this->whenLoaded('periods', fn () => $this->periods->map(fn (SubscriptionPeriod $period): array => [
                'id' => $period->id,
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'status' => $period->status->value,
                'status_label' => $period->status->label(),
                'invoice_id' => $period->invoice_id,
                'override_reason' => $period->override_reason,
                'can_undo' => in_array($period->status->value, ['overridden', 'waived'], true)
                    && $this->current_period_end?->equalTo($period->period_end),
            ])->all()),
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
