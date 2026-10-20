<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
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
            'spent_on' => $this->spent_on->toDateString(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'vendor' => $this->vendor,
            'description' => $this->description,
            'amount' => $this->amount,
            'amount_rupees' => Money::toRupees($this->amount),
            'gst_amount' => $this->gst_amount,
            'gst_amount_rupees' => $this->gst_amount > 0 ? Money::toRupees($this->gst_amount) : '',
            'net_cost' => $this->netCost(),
            'payment_method' => $this->payment_method?->value,
            'reference' => $this->reference,
            'is_recurring' => $this->is_recurring,
            'has_receipt' => $this->receipt_path !== null,
            'notes' => $this->notes,
        ];
    }
}
