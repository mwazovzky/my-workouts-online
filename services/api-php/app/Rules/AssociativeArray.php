<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AssociativeArray implements ValidationRule
{
    /**
     * Laravel's `array` rule accepts JSON arrays and objects alike, because both
     * decode to PHP arrays. Where the contract says object (a map of locales or
     * of translatable fields), a JSON list must be rejected instead of being
     * silently read as "no keys". An empty array is allowed; `required` decides
     * whether emptiness is acceptable.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) && $value !== [] && array_is_list($value)) {
            $fail(__('The :attribute field must be an object.'));
        }
    }
}
