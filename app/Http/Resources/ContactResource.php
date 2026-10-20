<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
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
            'designation' => $this->designation,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'whatsapp_target' => $this->whatsappTarget(),
            'whatsapp_opted_in' => $this->whatsapp_opted_in_at !== null,
            'whatsapp_opted_in_at' => $this->whatsapp_opted_in_at?->toIso8601String(),
            'is_primary' => $this->is_primary,
            'receives_invoices' => $this->receives_invoices,
        ];
    }
}
