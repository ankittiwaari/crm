<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'code' => $this->code,
            'description' => $this->description,
            'sac_code' => $this->sac_code,
            'tax_rate' => $this->tax_rate,
            'list_price' => $this->list_price,
            'list_price_rupees' => $this->list_price === null ? null : Money::toRupees($this->list_price),
            'default_billing_cycle' => $this->default_billing_cycle->value,
            'default_billing_cycle_label' => $this->default_billing_cycle->label(),
            'unit_label' => $this->unit_label,
            'is_active' => $this->is_active,
            'usage' => $this->whenLoaded('subscriptions', fn () => $this->usage()),
        ];
    }

    /**
     * How many clients use the product and the range of negotiated yearly values.
     *
     * @return array{clients: int, annual_value: int, min_annual: int|null, max_annual: int|null}
     */
    private function usage(): array
    {
        $annual = $this->subscriptions
            ->map(fn (Subscription $subscription): int => $subscription->annualValue())
            ->filter();

        return [
            'clients' => $this->subscriptions->pluck('client_id')->unique()->count(),
            'annual_value' => (int) $annual->sum(),
            'min_annual' => $annual->min(),
            'max_annual' => $annual->max(),
        ];
    }
}
