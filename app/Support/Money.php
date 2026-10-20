<?php

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;

/**
 * Amounts are stored as integer paise; this converts to and from rupee strings.
 */
final class Money
{
    /**
     * Convert a rupee amount such as "1,25,000.50" to paise.
     */
    public static function toPaise(string|int|float $rupees): int
    {
        $normalized = str_replace([',', ' ', '₹'], '', (string) $rupees);

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException("[{$rupees}] is not a valid rupee amount.");
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    /**
     * Convert paise to a plain rupee string with two decimals, e.g. "125000.50".
     */
    public static function toRupees(int $paise): string
    {
        return intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format paise with Indian digit grouping, e.g. 12500000 → "₹1,25,000.00".
     */
    public static function format(int $paise, bool $withSymbol = true): string
    {
        $formatter = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        $formatted = $formatter->format(abs($paise) / 100);

        return ($paise < 0 ? '-' : '').($withSymbol ? '₹' : '').$formatted;
    }
}
