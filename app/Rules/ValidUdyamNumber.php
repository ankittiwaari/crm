<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidUdyamNumber implements ValidationRule
{
    public const string PATTERN = '/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, strtoupper(trim($value))) !== 1) {
            $fail('The :attribute must look like UDYAM-KA-03-0012345.');
        }
    }
}
