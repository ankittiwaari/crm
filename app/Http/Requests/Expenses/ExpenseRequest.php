<?php

namespace App\Http\Requests\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Subscriptions\ConvertsRupees;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseRequest extends FormRequest
{
    use ConvertsRupees;

    protected function prepareForValidation(): void
    {
        $this->stripRupeeFormatting(['amount', 'gst_amount']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', $this->rupeesRule()],
            'gst_amount' => ['nullable', $this->rupeesRule()],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'is_recurring' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:5120'],
            'remove_receipt' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['amount', 'gst_amount']) || blank($this->input('gst_amount'))) {
                    return;
                }

                if (Money::toPaise((string) $this->input('gst_amount')) > Money::toPaise((string) $this->input('amount'))) {
                    $validator->errors()->add('gst_amount', 'The GST cannot be more than the amount paid.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter the amount paid in rupees, e.g. 1180.',
            'gst_amount.regex' => 'Enter the GST in rupees, e.g. 180.',
            'spent_on.before_or_equal' => 'The date cannot be in the future.',
            'description.required' => 'What was this for?',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function expenseAttributes(): array
    {
        $data = $this->safe()->except(['receipt', 'remove_receipt']);
        $data['amount'] = Money::toPaise((string) $data['amount']);
        $data['gst_amount'] = filled($data['gst_amount'] ?? null) ? Money::toPaise((string) $data['gst_amount']) : 0;

        return $data;
    }
}
