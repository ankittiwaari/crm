<?php

namespace App\Http\Requests\BusinessProfile;

use App\Enums\IndianState;
use App\Enums\MsmeCategory;
use App\Models\CompanyProfile;
use App\Rules\ValidGstin;
use App\Rules\ValidUdyamNumber;
use App\Support\Gstin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBusinessProfileRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $normalizers = [
            'gstin' => Gstin::normalize(...),
            'pan' => fn (string $value): string => strtoupper(trim($value)),
            'udyam_number' => fn (string $value): string => strtoupper(trim($value)),
            'bank_ifsc' => fn (string $value): string => strtoupper(trim($value)),
            'invoice_prefix' => fn (string $value): string => strtoupper(trim($value)),
            'bank_account_number' => fn (string $value): string => (string) preg_replace('/\s+/', '', $value),
        ];

        foreach ($normalizers as $key => $normalize) {
            if ($this->filled($key)) {
                $this->merge([$key => $normalize((string) $this->input($key))]);
            }
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
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', Rule::enum(IndianState::class)],
            'pincode' => ['nullable', 'digits:6'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],

            'gstin' => ['nullable', new ValidGstin],
            'gst_registered_on' => ['nullable', 'date', 'before_or_equal:today'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],

            'udyam_number' => ['nullable', new ValidUdyamNumber],
            'msme_category' => ['nullable', 'required_with:udyam_number', Rule::enum(MsmeCategory::class)],
            'msme_category_effective_from' => ['nullable', 'date'],
            'show_msme_clause' => ['boolean'],
            'udyam_certificate' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'remove_udyam_certificate' => ['boolean'],

            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'regex:/^[0-9]{9,18}$/'],
            'bank_ifsc' => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'upi_id' => ['nullable', 'regex:/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/'],

            'invoice_prefix' => ['required', 'alpha_num', 'max:10'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_notes' => ['nullable', 'string', 'max:2000'],
            'invoice_terms' => ['nullable', 'string', 'max:2000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('payment_terms_days')) {
                    return;
                }

                if ($this->boolean('show_msme_clause') && $this->integer('payment_terms_days') > CompanyProfile::MSME_MAX_PAYMENT_DAYS) {
                    $validator->errors()->add(
                        'payment_terms_days',
                        'Payment terms can be at most 45 days while the MSME clause is printed on invoices.',
                    );
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
            'pan.regex' => 'The PAN must look like ABCDE1234F.',
            'bank_account_number.regex' => 'The account number must be 9 to 18 digits.',
            'bank_ifsc.regex' => 'The IFSC must look like HDFC0001234.',
            'upi_id.regex' => 'The UPI ID must look like name@bank.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'gstin' => 'GSTIN',
            'pan' => 'PAN',
            'udyam_number' => 'Udyam number',
            'msme_category' => 'MSME category',
            'bank_ifsc' => 'IFSC',
            'upi_id' => 'UPI ID',
            'state_code' => 'state',
        ];
    }
}
