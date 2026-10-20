<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ActivityType: string
{
    use HasOptions;

    case Call = 'call';
    case Meeting = 'meeting';
    case Demo = 'demo';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            default => ucfirst($this->value),
        };
    }
}
