<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person at a client, e.g. the principal or the accountant.
 *
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $designation
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $whatsapp_number
 * @property CarbonImmutable|null $whatsapp_opted_in_at
 * @property bool $is_primary
 * @property bool $receives_invoices
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'designation', 'email', 'phone', 'whatsapp_number', 'whatsapp_opted_in_at', 'is_primary', 'receives_invoices'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_opted_in_at' => 'datetime',
            'is_primary' => 'boolean',
            'receives_invoices' => 'boolean',
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
     * The number to message: the WhatsApp number, or the phone when none is given.
     */
    public function whatsappTarget(): ?string
    {
        return $this->whatsapp_number ?? $this->phone;
    }
}
