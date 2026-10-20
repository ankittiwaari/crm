<?php

namespace App\Http\Requests\Subscriptions;

use App\Enums\PricingModel;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RateChangeRequest extends FormRequest
{
    use ConvertsRupees;

    protected function prepareForValidation(): void
    {
        $this->stripRupeeFormatting(['rate']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pricing_model' => ['required', Rule::enum(PricingModel::class)],
            'rate' => ['required', $this->rupeesRule()],
            'quantity' => ['nullable', 'required_if:pricing_model,per_unit', 'integer', 'min:1', 'max:1000000'],
            'effective_from' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->rupeeMessages(),
            'reason.required' => 'Say why the rate changed, e.g. "Renewal increase" or "Added transport module".',
            'quantity.required_if' => 'Enter how many units (e.g. students) are being charged for.',
        ];
    }

    /**
     * @return array{pricing_model: string, rate: int, quantity: int|null, effective_from: string, reason: string}
     */
    public function rateChange(): array
    {
        $quantity = $this->validated('quantity');

        return [
            'pricing_model' => (string) $this->validated('pricing_model'),
            'rate' => Money::toPaise((string) $this->validated('rate')),
            'quantity' => $quantity === null ? null : (int) $quantity,
            'effective_from' => (string) $this->validated('effective_from'),
            'reason' => (string) $this->validated('reason'),
        ];
    }
}
