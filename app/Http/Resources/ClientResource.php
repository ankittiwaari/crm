<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'billing_address' => $this->billing_address,
            'city' => $this->city,
            'state_code' => $this->state_code,
            'state_name' => $this->state()?->label(),
            'pincode' => $this->pincode,
            'gstin' => $this->gstin,
            'notes' => $this->notes,
            'whatsapp_automation' => $this->whatsapp_automation,
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'primary_contact' => $this->whenLoaded('primaryContact', fn () => $this->primaryContact ? ContactResource::make($this->primaryContact)->resolve($request) : null),
            'contacts' => $this->whenLoaded('contacts', fn () => ContactResource::collection($this->contacts)->resolve($request)),
            'annual_value' => $this->whenLoaded('subscriptions', fn () => $this->subscriptions->sum(
                fn (Subscription $subscription): int => $subscription->annualValue(),
            )),
            'next_renewal_on' => $this->whenLoaded('subscriptions', fn () => $this->subscriptions
                ->filter(fn (Subscription $subscription): bool => $subscription->status->isLive())
                ->pluck('current_period_end')
                ->filter()
                ->min()
                ?->toDateString()),
        ];
    }
}
