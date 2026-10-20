<?php

namespace App\Models;

use App\Enums\MessageChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A WhatsApp or email message sent to a client, e.g. an invoice.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $contact_id
 * @property int|null $user_id
 * @property string|null $messageable_type
 * @property int|null $messageable_id
 * @property MessageChannel $channel
 * @property string $template
 * @property string $recipient
 * @property string|null $body
 * @property CarbonImmutable $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['client_id', 'contact_id', 'user_id', 'channel', 'template', 'recipient', 'body', 'sent_at'])]
class Message extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'sent_at' => 'datetime',
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
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }
}
