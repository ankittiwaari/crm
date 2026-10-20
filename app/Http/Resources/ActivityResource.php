<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
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
            'body' => $this->body,
            'happened_at' => $this->happened_at->toIso8601String(),
            'follow_up_on' => $this->follow_up_on?->toDateString(),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
        ];
    }
}
