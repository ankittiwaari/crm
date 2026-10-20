<?php

namespace App\Enums;

enum MessageChannel: string
{
    case WhatsAppLink = 'whatsapp_link';
    case WhatsAppApi = 'whatsapp_api';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::WhatsAppLink => 'WhatsApp',
            self::WhatsAppApi => 'WhatsApp (automatic)',
            self::Email => 'Email',
        };
    }
}
