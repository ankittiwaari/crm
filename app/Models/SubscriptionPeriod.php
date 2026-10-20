<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One billing period of a subscription in the renewal ledger: awaiting payment, paid,
 * renewed manually (override) or extended for free.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int|null $invoice_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property PeriodStatus $status
 * @property CarbonImmutable|null $settled_at
 * @property string|null $override_reason
 * @property int|null $overridden_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['invoice_id', 'period_start', 'period_end', 'status', 'settled_at', 'override_reason', 'overridden_by'])]
class SubscriptionPeriod extends Model
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
            'status' => PeriodStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
