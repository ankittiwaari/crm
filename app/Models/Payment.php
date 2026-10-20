<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received against an invoice. TDS the client deducted counts towards settling it.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $user_id
 * @property int $amount
 * @property int $tds_amount
 * @property CarbonImmutable $paid_on
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'amount', 'tds_amount', 'paid_on', 'method', 'reference', 'notes'])]
class Payment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tds_amount' => 'integer',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * How much of the invoice this payment settles.
     */
    public function settles(): int
    {
        return $this->amount + $this->tds_amount;
    }
}
