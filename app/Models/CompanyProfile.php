<?php

namespace App\Models;

use App\Enums\IndianState;
use App\Enums\MsmeCategory;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Your own business details, printed on every invoice. There is only ever one row.
 *
 * @property int $id
 * @property string|null $legal_name
 * @property string|null $trade_name
 * @property string|null $logo_path
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $state_code
 * @property string|null $pincode
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $gstin
 * @property CarbonImmutable|null $gst_registered_on
 * @property string|null $pan
 * @property string|null $udyam_number
 * @property MsmeCategory|null $msme_category
 * @property CarbonImmutable|null $msme_category_effective_from
 * @property string|null $udyam_certificate_path
 * @property bool $show_msme_clause
 * @property string|null $bank_account_name
 * @property string|null $bank_name
 * @property string|null $bank_branch
 * @property string|null $bank_account_number
 * @property string|null $bank_ifsc
 * @property string|null $upi_id
 * @property string $invoice_prefix
 * @property int $payment_terms_days
 * @property string|null $invoice_notes
 * @property string|null $invoice_terms
 * @property string|null $signatory_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'legal_name', 'trade_name', 'address_line', 'city', 'state_code', 'pincode', 'email', 'phone', 'website',
    'gstin', 'gst_registered_on', 'pan',
    'udyam_number', 'msme_category', 'msme_category_effective_from', 'show_msme_clause',
    'bank_account_name', 'bank_name', 'bank_branch', 'bank_account_number', 'bank_ifsc', 'upi_id',
    'invoice_prefix', 'payment_terms_days', 'invoice_notes', 'invoice_terms', 'signatory_name',
])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    /**
     * The longest payment term allowed while the MSME clause is printed (MSMED Act, Sec. 15).
     */
    public const int MSME_MAX_PAYMENT_DAYS = 45;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gst_registered_on' => 'date',
            'msme_category' => MsmeCategory::class,
            'msme_category_effective_from' => 'date',
            'show_msme_clause' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }

    /**
     * The single business profile, created empty on first use.
     */
    public static function current(): self
    {
        return self::query()->oldest('id')->firstOrCreate();
    }

    /**
     * @return HasMany<CompanyProfileChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(CompanyProfileChange::class);
    }

    public function state(): ?IndianState
    {
        return IndianState::tryFrom((string) $this->state_code);
    }

    public function displayName(): ?string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * The details every invoice needs before it can be issued.
     *
     * @return list<string>
     */
    public function missingInvoiceDetails(): array
    {
        return array_keys(array_filter([
            'Legal name' => blank($this->legal_name),
            'Address' => blank($this->address_line) || blank($this->state_code),
            'GSTIN' => blank($this->gstin),
            'Bank or UPI details' => blank($this->bank_account_number) && blank($this->upi_id),
        ]));
    }
}
