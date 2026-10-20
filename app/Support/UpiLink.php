<?php

namespace App\Support;

/**
 * A UPI payment link (upi://pay) that opens any UPI app with the payee and amount filled in.
 */
final class UpiLink
{
    public static function make(string $upiId, ?string $payeeName, int $amountPaise, ?string $note = null): string
    {
        return 'upi://pay?'.http_build_query(array_filter([
            'pa' => $upiId,
            'pn' => $payeeName,
            'am' => Money::toRupees($amountPaise),
            'cu' => 'INR',
            'tn' => $note,
        ], fn (?string $value): bool => filled($value)), '', '&', PHP_QUERY_RFC3986);
    }
}
