<?php

namespace App\Http\Resources;

use App\Models\RateRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RateRevision
 */
class RateRevisionResource extends JsonResource
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
            'old_amount' => $this->oldAmountPerCycle(),
            'new_amount' => $this->newAmountPerCycle(),
            'new_pricing_model' => $this->new_pricing_model->value,
            'new_rate' => $this->new_rate,
            'new_quantity' => $this->new_quantity,
            'effective_from' => $this->effective_from->toDateString(),
            'reason' => $this->reason,
        ];
    }
}
