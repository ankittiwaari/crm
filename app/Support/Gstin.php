<?php

namespace App\Support;

use App\Enums\IndianState;

/**
 * A GST Identification Number: 2-digit state code, 10-character PAN,
 * entity number, the letter Z and a mod-36 check character.
 */
final class Gstin
{
    private const string PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    private const string CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private function __construct(public readonly string $value) {}

    public static function tryFrom(?string $value): ?self
    {
        $value = self::normalize($value);

        return self::isValid($value) ? new self($value) : null;
    }

    public static function normalize(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1
            && IndianState::tryFrom(substr($value, 0, 2)) !== null
            && self::checkCharacter(substr($value, 0, 14)) === $value[14];
    }

    /**
     * Compute the check character for the first 14 characters of a GSTIN.
     */
    public static function checkCharacter(string $firstFourteen): string
    {
        $sum = 0;

        foreach (str_split($firstFourteen) as $index => $character) {
            $product = strpos(self::CHARACTERS, $character) * ($index % 2 === 0 ? 1 : 2);
            $sum += intdiv($product, 36) + $product % 36;
        }

        return self::CHARACTERS[(36 - $sum % 36) % 36];
    }

    public function state(): IndianState
    {
        return IndianState::from(substr($this->value, 0, 2));
    }

    public function pan(): string
    {
        return substr($this->value, 2, 10);
    }
}
