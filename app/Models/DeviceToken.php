<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone registered for push notifications (Firebase Cloud Messaging token).
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property string|null $device_name
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'token', 'platform', 'device_name', 'last_seen_at'])]
class DeviceToken extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
