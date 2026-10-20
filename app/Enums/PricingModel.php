<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PricingModel: string
{
    use HasOptions;

    case Flat = 'flat';
    case PerUnit = 'per_unit';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat rate',
            self::PerUnit => 'Per unit',
        };
    }
}
