<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum InvoiceStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Unpaid',
            self::PartiallyPaid => 'Partly paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Issued invoices with money still owed.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Issued, self::PartiallyPaid];
    }
}
