<?php

namespace App\Http\Requests\Invoices;

use App\Enums\PaymentMethod;
use App\Http\Requests\Subscriptions\ConvertsRupees;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    use ConvertsRupees;

    protected function prepareForValidation(): void
    {
        $this->stripRupeeFormatting(['amount', 'tds_amount']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', $this->rupeesRule()],
            'tds_amount' => ['nullable', $this->rupeesRule()],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter the amount received in rupees, e.g. 51840.',
            'tds_amount.regex' => 'Enter the TDS in rupees, e.g. 4800.',
            'paid_on.before_or_equal' => 'The payment date cannot be in the future.',
        ];
    }

    /**
     * @return array{amount: int, tds_amount: int, paid_on: string, method: string, reference: string|null, notes: string|null}
     */
    public function payment(): array
    {
        return [
            'amount' => Money::toPaise((string) $this->validated('amount')),
            'tds_amount' => filled($this->validated('tds_amount')) ? Money::toPaise((string) $this->validated('tds_amount')) : 0,
            'paid_on' => (string) $this->validated('paid_on'),
            'method' => (string) $this->validated('method'),
            'reference' => $this->validated('reference'),
            'notes' => $this->validated('notes'),
        ];
    }
}
