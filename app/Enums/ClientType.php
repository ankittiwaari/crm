<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClientType: string
{
    use HasOptions;

    case School = 'school';
    case Pharmacy = 'pharmacy';
    case Clinic = 'clinic';
    case Retail = 'retail';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
