<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::BankTransfer => 'Bank transfer',
            self::Cheque => 'Cheque',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Other => 'Other',
        };
    }
}
