<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MsmeCategory: string
{
    use HasOptions;

    case Micro = 'micro';
    case Small = 'small';
    case Medium = 'medium';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
