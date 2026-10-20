<?php

namespace App\Support;

/**
 * Normalises phone numbers to E.164 (+919845012345), assuming India when no country code is given.
 */
final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        $hasPlus = str_starts_with(trim((string) $value), '+');

        if ($digits === '') {
            return null;
        }

        return match (true) {
            $hasPlus => '+'.$digits,
            strlen($digits) === 10 => '+91'.$digits,
            strlen($digits) === 11 && str_starts_with($digits, '0') => '+91'.substr($digits, 1),
            strlen($digits) === 12 && str_starts_with($digits, '91') => '+'.$digits,
            default => '+'.$digits,
        };
    }

    public static function isValid(?string $e164): bool
    {
        return $e164 !== null && preg_match('/^\+[1-9]\d{7,14}$/', $e164) === 1;
    }
}
