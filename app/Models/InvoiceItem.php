<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $subscription_id
 * @property int|null $product_id
 * @property string $description
 * @property string $sac_code
 * @property CarbonImmutable|null $period_start
 * @property CarbonImmutable|null $period_end
 * @property int $quantity
 * @property int $unit_price
 * @property int $discount
 * @property string $tax_rate
 * @property int $taxable_amount
 * @property int $cgst
 * @property int $sgst
 * @property int $igst
 * @property int $line_total
 * @property int $position
 */
#[Fillable([
    'subscription_id', 'product_id', 'description', 'sac_code', 'period_start', 'period_end', 'quantity',
    'unit_price', 'discount', 'tax_rate', 'taxable_amount', 'cgst', 'sgst', 'igst', 'line_total', 'position',
])]
class InvoiceItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'discount' => 'integer',
            'tax_rate' => 'decimal:2',
            'taxable_amount' => 'integer',
            'cgst' => 'integer',
            'sgst' => 'integer',
            'igst' => 'integer',
            'line_total' => 'integer',
            'position' => 'integer',
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
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }
}
