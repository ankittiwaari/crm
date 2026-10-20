<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Message;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'number' => $this->number,
            'display_number' => $this->displayNumber(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->daysOverdue(),
            'client_id' => $this->client_id,
            'client_name' => $this->client_snapshot['name'] ?? $this->whenLoaded('client', fn () => $this->client->name),
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'tax_type' => $this->tax_type->value,
            'tax_type_label' => $this->tax_type->label(),
            'place_of_supply' => $this->place_of_supply,
            'subtotal' => $this->subtotal,
            'taxable_total' => $this->taxable_total,
            'cgst_total' => $this->cgst_total,
            'sgst_total' => $this->sgst_total,
            'igst_total' => $this->igst_total,
            'round_off' => $this->round_off,
            'total' => $this->total,
            'amount_settled' => $this->amount_settled,
            'balance_due' => $this->balanceDue(),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'finalised_at' => $this->finalised_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'subscription_id' => $item->subscription_id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'sac_code' => $item->sac_code,
                'period_start' => $item->period_start?->toDateString(),
                'period_end' => $item->period_end?->toDateString(),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_price_rupees' => Money::toRupees($item->unit_price),
                'discount' => $item->discount,
                'discount_rupees' => $item->discount > 0 ? Money::toRupees($item->discount) : '',
                'tax_rate' => (string) (float) $item->tax_rate,
                'taxable_amount' => $item->taxable_amount,
                'line_total' => $item->line_total,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'tds_amount' => $payment->tds_amount,
                'paid_on' => $payment->paid_on->toDateString(),
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ])->all()),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'channel' => $message->channel->value,
                'channel_label' => $message->channel->label(),
                'direction' => $message->direction,
                'status' => $message->status,
                'error' => $message->error,
                'recipient' => $message->recipient,
                'sent_at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
            ])->all()),
        ];
    }
}
