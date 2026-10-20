<?php

namespace App\Http\Requests\Clients;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreClientRequest extends ClientRequest
{
    /**
     * Get the validation rules that apply to the request, including an optional first contact.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'contact' => ['nullable', 'array'],
            'contact.name' => ['nullable', 'required_with:contact.phone,contact.designation', 'string', 'max:255'],
            'contact.designation' => ['nullable', 'string', 'max:100'],
            'contact.phone' => ['nullable', $this->phoneRule()],
            'contact.whatsapp_opt_in' => ['boolean'],
        ];
    }

    /**
     * The validated first contact, or null when none was entered.
     *
     * @return array<string, mixed>|null
     */
    public function contactAttributes(): ?array
    {
        $contact = $this->validated('contact');

        return filled($contact['name'] ?? null) ? $contact : null;
    }
}
