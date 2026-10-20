<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Money spent running the business. When GST was charged on it, the claimable part is kept
 * separately so the real cost excludes input tax credit.
 *
 * @property int $id
 * @property int|null $user_id
 * @property CarbonImmutable $spent_on
 * @property ExpenseCategory $category
 * @property string|null $vendor
 * @property string $description
 * @property int $amount Paise, including GST.
 * @property int $gst_amount Paise of claimable input GST.
 * @property PaymentMethod|null $payment_method
 * @property string|null $reference
 * @property bool $is_recurring
 * @property string|null $receipt_path
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'spent_on', 'category', 'vendor', 'description', 'amount', 'gst_amount',
    'payment_method', 'reference', 'is_recurring', 'notes',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'category' => ExpenseCategory::class,
            'amount' => 'integer',
            'gst_amount' => 'integer',
            'payment_method' => PaymentMethod::class,
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * The cost to the business: what was paid, minus GST you can claim back.
     */
    public function netCost(): int
    {
        return $this->amount - $this->gst_amount;
    }
}
