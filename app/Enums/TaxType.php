<?php

namespace App\Enums;

/**
 * Which GST applies: CGST + SGST within your state, IGST across states.
 */
enum TaxType: string
{
    case IntraState = 'intra_state';
    case InterState = 'inter_state';

    public function label(): string
    {
        return match ($this) {
            self::IntraState => 'Intra-state · CGST + SGST',
            self::InterState => 'Inter-state · IGST',
        };
    }
}
