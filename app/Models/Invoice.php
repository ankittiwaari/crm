<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\TaxType;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A GST tax invoice. Drafts can change; once finalised it gets a number and its amounts,
 * seller and client details are frozen.
 *
 * @property int $id
 * @property string|null $number
 * @property string|null $financial_year
 * @property int|null $sequence
 * @property int $client_id
 * @property InvoiceStatus $status
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $due_date
 * @property string|null $place_of_supply
 * @property TaxType $tax_type
 * @property array<string, mixed>|null $client_snapshot
 * @property array<string, mixed>|null $seller_snapshot
 * @property int $subtotal
 * @property int $discount_total
 * @property int $taxable_total
 * @property int $cgst_total
 * @property int $sgst_total
 * @property int $igst_total
 * @property int $round_off
 * @property int $total
 * @property int $amount_settled
 * @property string|null $notes
 * @property string|null $terms
 * @property CarbonImmutable|null $finalised_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'issue_date', 'due_date', 'notes', 'terms'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'tax_type' => TaxType::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'client_snapshot' => 'array',
            'seller_snapshot' => 'array',
            'sequence' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'taxable_total' => 'integer',
            'cgst_total' => 'integer',
            'sgst_total' => 'integer',
            'igst_total' => 'integer',
            'round_off' => 'integer',
            'total' => 'integer',
            'amount_settled' => 'integer',
            'finalised_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return MorphMany<Message, $this>
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    /**
     * Issued invoices with money still owed.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', InvoiceStatus::open());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_settled);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date->lt(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /**
     * Title for PDFs and messages: the number once issued, otherwise "Draft".
     */
    public function displayNumber(): string
    {
        return $this->number ?? 'Draft #'.$this->id;
    }

    public function fileName(): string
    {
        return str_replace('/', '-', $this->number ?? 'draft-'.$this->id).'.pdf';
    }
}
