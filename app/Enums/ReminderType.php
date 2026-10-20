<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ReminderType: string
{
    use HasOptions;

    case Renewal = 'renewal';
    case PromoReview = 'promo_review';
    case PaymentFollowUp = 'payment_follow_up';
    case FollowUp = 'follow_up';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Renewal => 'Renewal',
            self::PromoReview => 'Promo ending',
            self::PaymentFollowUp => 'Payment follow-up',
            self::FollowUp => 'Follow-up',
            self::General => 'Reminder',
        };
    }
}
