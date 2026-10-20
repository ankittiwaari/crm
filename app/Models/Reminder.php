<?php

namespace App\Models;

use App\Enums\ReminderType;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something to do on a given day: a renewal, a promo to review, a payment to chase or a follow-up call.
 *
 * @property int $id
 * @property int|null $client_id
 * @property int|null $user_id
 * @property string|null $remindable_type
 * @property int|null $remindable_id
 * @property ReminderType $type
 * @property string $title
 * @property string|null $notes
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable|null $completed_at
 * @property string|null $dedupe_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'user_id', 'type', 'title', 'notes', 'due_on', 'completed_at', 'dedupe_key'])]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'due_on' => 'date',
            'completed_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Open reminders due today or earlier.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function due(Builder $query): Builder
    {
        return $query->pending()->whereDate('due_on', '<=', today());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }
}
