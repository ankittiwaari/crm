<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SubscriptionStatus: string
{
    use HasOptions;

    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether the client is currently using (and paying for, or trialling) the product.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }

    /**
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::Trial, self::Active];
    }
}
