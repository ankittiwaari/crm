<?php

namespace App\Http\Requests\Subscriptions;

use App\Enums\BillingCycle;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a product to a client (with its rate) or editing a subscription's terms.
 * Rate changes on existing subscriptions go through RateChangeRequest so they are recorded.
 */
class SubscriptionRequest extends FormRequest
{
    use ConvertsRupees;

    protected function prepareForValidation(): void
    {
        $this->stripRupeeFormatting(['rate', 'setup_fee']);
    }

    private function isNew(): bool
    {
        return ! $this->route('subscription') instanceof Subscription;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'unit_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'setup_fee' => ['nullable', $this->rupeesRule()],
            'is_promotional' => ['boolean'],
            'promo_ends_on' => ['nullable', 'date'],
            'promo_note' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'current_period_end' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_renew' => ['boolean'],
            'renewal_notice_days' => ['required', 'integer', 'min:0', 'max:180'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->isNew()) {
            $rules += [
                'product_id' => ['required', Rule::exists(Product::class, 'id')->whereNull('deleted_at')],
                'pricing_model' => ['required', Rule::enum(PricingModel::class)],
                'rate' => ['required', $this->rupeesRule()],
                'quantity' => ['nullable', 'required_if:pricing_model,per_unit', 'integer', 'min:1', 'max:1000000'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->rupeeMessages(),
            'quantity.required_if' => 'Enter how many units (e.g. students) are being charged for.',
            'current_period_end.after_or_equal' => 'The current period cannot end before the subscription started.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['product_id' => 'product', 'unit_count' => 'unit count'];
    }

    /**
     * Validated subscription attributes with amounts in paise. Promo details are cleared when
     * the deal is not promotional.
     *
     * @return array<string, mixed>
     */
    public function subscriptionAttributes(): array
    {
        $data = $this->rupeesToPaise($this->validated(), ['rate', 'setup_fee']);

        if (! ($data['is_promotional'] ?? false)) {
            $data['promo_ends_on'] = null;
            $data['promo_note'] = null;
        }

        return $data;
    }
}
