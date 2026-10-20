<?php

namespace App\Models;

use App\Enums\PricingModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a subscription's negotiation history.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int|null $user_id
 * @property PricingModel|null $old_pricing_model
 * @property PricingModel $new_pricing_model
 * @property int|null $old_rate
 * @property int $new_rate
 * @property int|null $old_quantity
 * @property int $new_quantity
 * @property CarbonImmutable $effective_from
 * @property string $reason
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'user_id', 'old_pricing_model', 'new_pricing_model', 'old_rate', 'new_rate',
    'old_quantity', 'new_quantity', 'effective_from', 'reason',
])]
class RateRevision extends Model
{
    public const null UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_pricing_model' => PricingModel::class,
            'new_pricing_model' => PricingModel::class,
            'old_rate' => 'integer',
            'new_rate' => 'integer',
            'old_quantity' => 'integer',
            'new_quantity' => 'integer',
            'effective_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Amount per cycle after this change, in paise.
     */
    public function newAmountPerCycle(): int
    {
        return $this->new_pricing_model === PricingModel::PerUnit
            ? $this->new_rate * $this->new_quantity
            : $this->new_rate;
    }

    public function oldAmountPerCycle(): ?int
    {
        if ($this->old_rate === null) {
            return null;
        }

        return $this->old_pricing_model === PricingModel::PerUnit
            ? $this->old_rate * (int) $this->old_quantity
            : $this->old_rate;
    }
}
