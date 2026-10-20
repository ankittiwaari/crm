<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit entry recording one field of the business profile changing.
 *
 * @property int $id
 * @property int $company_profile_id
 * @property int|null $user_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property CarbonImmutable $created_at
 */
#[Fillable(['field', 'old_value', 'new_value', 'user_id'])]
class CompanyProfileChange extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<CompanyProfile, $this>
     */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
