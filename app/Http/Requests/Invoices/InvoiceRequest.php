<?php

namespace App\Http\Requests\Invoices;

use App\Http\Requests\Products\ProductRequest;
use App\Http\Requests\Subscriptions\ConvertsRupees;
use App\Models\Client;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    use ConvertsRupees;

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $this->merge(['items' => array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            foreach (['unit_price', 'discount'] as $key) {
                if (isset($item[$key]) && is_string($item[$key])) {
                    $item[$key] = str_replace([',', ' ', '₹'], '', $item[$key]);
                }
            }

            return $item;
        }, $items)]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists(Client::class, 'id')->whereNull('deleted_at')],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.subscription_id' => ['nullable', 'integer', Rule::exists(Subscription::class, 'id')],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists(Product::class, 'id')],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.sac_code' => ['required', 'digits_between:4,8'],
            'items.*.period_start' => ['nullable', 'date'],
            'items.*.period_end' => ['nullable', 'date', 'after_or_equal:items.*.period_start'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', $this->rupeesRule()],
            'items.*.discount' => ['nullable', $this->rupeesRule()],
            'items.*.tax_rate' => ['required', Rule::in(ProductRequest::TAX_RATES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one line.',
            'items.*.description.required' => 'Describe what this line is for.',
            'items.*.unit_price.regex' => 'Enter the rate in rupees, e.g. 48000.',
            'items.*.discount.regex' => 'Enter the discount in rupees, e.g. 2000.',
            'items.*.tax_rate.in' => 'Choose a GST rate: 0, 5, 12, 18 or 28%.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'items.*.sac_code' => 'SAC code',
            'items.*.quantity' => 'quantity',
            'items.*.period_end' => 'period end',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceAttributes(): array
    {
        return $this->safe()->only(['client_id', 'issue_date', 'due_date', 'notes', 'terms']);
    }

    /**
     * Validated lines with amounts in paise.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return array_values(array_map(
            fn (array $item): array => $this->rupeesToPaise($item, ['unit_price', 'discount']),
            $this->validated('items'),
        ));
    }
}
