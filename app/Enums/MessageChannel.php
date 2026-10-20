<?php

namespace App\Enums;

enum MessageChannel: string
{
    case WhatsAppLink = 'whatsapp_link';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::WhatsAppLink => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}
