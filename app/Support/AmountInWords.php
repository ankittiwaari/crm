<?php

namespace App\Support;

/**
 * Spells out a rupee amount with Indian numbering (thousand, lakh, crore),
 * e.g. 6372050 paise → "Indian Rupees Sixty-Three Thousand Seven Hundred Twenty and Fifty Paise Only".
 */
final class AmountInWords
{
    private const array ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const array TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function rupees(int $paise): string
    {
        $rupees = intdiv($paise, 100);
        $remainder = $paise % 100;

        $words = 'Indian Rupees '.($rupees === 0 ? 'Zero' : self::number($rupees));

        if ($remainder > 0) {
            $words .= ' and '.self::number($remainder).' Paise';
        }

        return $words.' Only';
    }

    /**
     * Spell out a whole number using the Indian system.
     */
    public static function number(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        foreach ([[10_000_000, 'Crore'], [100_000, 'Lakh'], [1_000, 'Thousand'], [100, 'Hundred']] as [$size, $name]) {
            if ($number >= $size) {
                $count = intdiv($number, $size);
                $parts[] = ($size === 10_000_000 ? self::number($count) : self::belowHundred($count)).' '.$name;
                $number %= $size;
            }
        }

        if ($number > 0) {
            $parts[] = self::belowHundred($number);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $ones = $number % 10;

        return self::TENS[intdiv($number, 10)].($ones > 0 ? '-'.self::ONES[$ones] : '');
    }
}
