<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClientStatus: string
{
    use HasOptions;

    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';
    case Churned = 'churned';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
