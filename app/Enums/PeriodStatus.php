<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Overridden = 'overridden';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Overridden => 'Renewed manually',
            self::Waived => 'Free extension',
        };
    }

    /**
     * Whether this period counts as renewed.
     */
    public function isSettled(): bool
    {
        return $this !== self::PendingPayment;
    }
}
