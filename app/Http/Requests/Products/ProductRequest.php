<?php

namespace App\Http\Requests\Products;

use App\Enums\BillingCycle;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * GST rates that apply to goods and services in India.
     */
    public const array TAX_RATES = ['0', '5', '12', '18', '28'];

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        if ($this->filled('list_price')) {
            $this->merge(['list_price' => str_replace([',', ' ', '₹'], '', (string) $this->input('list_price'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'alpha_dash', 'max:20',
                Rule::unique(Product::class)->ignore($product instanceof Product ? $product->id : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sac_code' => ['required', 'digits_between:4,8'],
            'tax_rate' => ['required', Rule::in(self::TAX_RATES)],
            'list_price' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'default_billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'list_price.regex' => 'Enter the price in rupees, e.g. 45000 or 45000.50.',
            'tax_rate.in' => 'Choose a GST rate: 0, 5, 12, 18 or 28%.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sac_code' => 'SAC code', 'tax_rate' => 'GST rate'];
    }

    /**
     * The validated input with the price converted to paise.
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $data = $this->validated();
        $data['list_price'] = filled($data['list_price'] ?? null) ? Money::toPaise($data['list_price']) : null;

        return $data;
    }
}
