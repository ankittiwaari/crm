<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\IndianState;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property ClientType $type
 * @property ClientStatus $status
 * @property string|null $source
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $billing_address
 * @property string|null $city
 * @property string|null $state_code
 * @property string|null $pincode
 * @property string|null $gstin
 * @property bool $whatsapp_automation
 * @property string|null $notes
 * @property CarbonImmutable|null $last_activity_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'type', 'status', 'source', 'email', 'phone', 'website', 'billing_address', 'city', 'state_code', 'pincode', 'gstin', 'whatsapp_automation', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'status' => ClientStatus::class,
            'last_activity_at' => 'datetime',
            'whatsapp_automation' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasOne<Contact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The contact invoices go to: the one marked to receive invoices, else the main contact.
     */
    public function billingContact(): ?Contact
    {
        return $this->contacts()
            ->orderByDesc('receives_invoices')
            ->orderByDesc('is_primary')
            ->oldest('id')
            ->first();
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function state(): ?IndianState
    {
        return IndianState::tryFrom((string) $this->state_code);
    }

    /**
     * Match a name, city, phone, GSTIN or contact name.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        return $query->where(function (Builder $query) use ($like, $digits): void {
            $query->where('name', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('gstin', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas('contacts', fn (Builder $contacts) => $contacts->where('name', 'like', $like));

            if (strlen($digits) >= 4) {
                $query->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhereHas('contacts', fn (Builder $contacts) => $contacts
                        ->where('phone', 'like', "%{$digits}%")
                        ->orWhere('whatsapp_number', 'like', "%{$digits}%"));
            }
        });
    }
}
