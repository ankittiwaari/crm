<?php

namespace App\Http\Requests\Clients;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePhoneNumbers(['phone', 'whatsapp_number']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', $this->phoneRule()],
            'whatsapp_number' => ['nullable', $this->phoneRule()],
            'whatsapp_opt_in' => ['boolean'],
            'is_primary' => ['boolean'],
            'receives_invoices' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 98450 12345.',
            'whatsapp_number.regex' => 'Enter a valid WhatsApp number, e.g. 98450 12345.',
        ];
    }
}
