<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\IndianState;
use App\Rules\ValidGstin;
use App\Support\Gstin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    use NormalizesPhoneNumbers;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePhoneNumbers(['phone', 'contact.phone']);

        if ($this->filled('gstin')) {
            $this->merge(['gstin' => Gstin::normalize($this->input('gstin'))]);
        }
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
            'type' => ['required', Rule::enum(ClientType::class)],
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'source' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', $this->phoneRule()],
            'website' => ['nullable', 'url', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', Rule::enum(IndianState::class)],
            'pincode' => ['nullable', 'digits:6'],
            'gstin' => ['nullable', new ValidGstin],
            'whatsapp_automation' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 98450 12345.',
            'contact.phone.regex' => 'Enter a valid phone number, e.g. 98450 12345.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'gstin' => 'GSTIN',
            'state_code' => 'state',
            'contact.name' => 'contact name',
            'contact.phone' => 'contact phone',
        ];
    }

    /**
     * The validated client fields, with the state taken from the GSTIN when there is one.
     *
     * @return array<string, mixed>
     */
    public function clientAttributes(): array
    {
        $data = $this->safe()->except('contact');

        if ($gstin = Gstin::tryFrom($data['gstin'] ?? null)) {
            $data['state_code'] = $gstin->state()->value;
        }

        return $data;
    }
}
