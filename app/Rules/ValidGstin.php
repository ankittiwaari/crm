<?php

namespace App\Rules;

use App\Support\Gstin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidGstin implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Gstin::tryFrom($value) === null) {
            $fail('The :attribute is not a valid GSTIN. Check the format and the last (check) character.');
        }
    }
}
