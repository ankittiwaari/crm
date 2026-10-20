<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client using a product at a negotiated rate.
 *
 * @property int $id
 * @property int $client_id
 * @property int $product_id
 * @property SubscriptionStatus $status
 * @property BillingCycle $billing_cycle
 * @property PricingModel $pricing_model
 * @property int $rate Paise per cycle (flat) or per unit per cycle.
 * @property int $quantity
 * @property int|null $unit_count
 * @property int|null $setup_fee Paise.
 * @property bool $is_promotional
 * @property CarbonImmutable|null $promo_ends_on
 * @property string|null $promo_note
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property bool $auto_renew
 * @property int $renewal_notice_days
 * @property int $grace_days
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'product_id', 'status', 'billing_cycle', 'pricing_model', 'rate', 'quantity', 'unit_count', 'setup_fee',
    'is_promotional', 'promo_ends_on', 'promo_note', 'start_date', 'current_period_start', 'current_period_end',
    'auto_renew', 'renewal_notice_days', 'grace_days', 'notes',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_cycle' => BillingCycle::class,
            'pricing_model' => PricingModel::class,
            'rate' => 'integer',
            'quantity' => 'integer',
            'unit_count' => 'integer',
            'setup_fee' => 'integer',
            'is_promotional' => 'boolean',
            'promo_ends_on' => 'date',
            'start_date' => 'date',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'auto_renew' => 'boolean',
            'renewal_notice_days' => 'integer',
            'grace_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return HasMany<RateRevision, $this>
     */
    public function rateRevisions(): HasMany
    {
        return $this->hasMany(RateRevision::class);
    }

    /**
     * @return HasMany<SubscriptionPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class);
    }

    /**
     * Trial or active subscriptions.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function live(Builder $query): Builder
    {
        return $query->whereIn('status', SubscriptionStatus::live());
    }

    /**
     * Live recurring subscriptions renewing between today and the given date.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function renewingBy(Builder $query, CarbonImmutable $date): Builder
    {
        return $query->live()
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [today(), $date]);
    }

    /**
     * Amount billed each cycle, in paise, before GST.
     */
    public function amountPerCycle(): int
    {
        return $this->pricing_model === PricingModel::PerUnit
            ? $this->rate * $this->quantity
            : $this->rate;
    }

    /**
     * Recurring value per year, in paise (0 for one-time or not live).
     */
    public function annualValue(): int
    {
        return $this->status->isLive()
            ? $this->amountPerCycle() * $this->billing_cycle->perYear()
            : 0;
    }

    public function daysUntilRenewal(): ?int
    {
        return $this->current_period_end === null
            ? null
            : (int) today()->diffInDays($this->current_period_end, false);
    }

    /**
     * Where the subscription is in its renewal cycle:
     * "current", "in_grace" (period ended, still within the grace days) or "lapsed".
     */
    public function renewalState(): ?string
    {
        if ($this->current_period_end === null || ! $this->status->isLive()) {
            return null;
        }

        return match (true) {
            $this->current_period_end->gte(today()) => 'current',
            $this->graceEndsOn()?->gte(today()) ?? false => 'in_grace',
            default => 'lapsed',
        };
    }

    public function graceEndsOn(): ?CarbonImmutable
    {
        return $this->current_period_end?->addDays($this->grace_days);
    }

    /**
     * The period after the current one, e.g. 27 Oct 2026 – 26 Oct 2027.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function nextPeriod(): ?array
    {
        if ($this->current_period_end === null) {
            return null;
        }

        $start = $this->current_period_end->addDay();
        $end = $this->billing_cycle->periodEnd($start);

        return $end === null ? null : [$start, $end];
    }
}
