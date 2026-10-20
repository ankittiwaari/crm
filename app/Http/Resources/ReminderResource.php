<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reminder
 */
class ReminderResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'notes' => $this->notes,
            'due_on' => $this->due_on->toDateString(),
            'is_overdue' => $this->completed_at === null && $this->due_on->lt(today()),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'link' => $this->link(),
        ];
    }

    /**
     * Where to go to act on the reminder.
     */
    private function link(): ?string
    {
        return match ($this->remindable_type) {
            (new Invoice)->getMorphClass() => route('invoices.show', $this->remindable_id),
            (new Subscription)->getMorphClass(), (new Activity)->getMorphClass() => $this->client_id ? route('clients.show', $this->client_id) : null,
            default => $this->client_id ? route('clients.show', $this->client_id) : null,
        };
    }
}
