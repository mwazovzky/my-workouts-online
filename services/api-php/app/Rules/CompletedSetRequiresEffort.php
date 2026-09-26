<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CompletedSetRequiresEffort implements ValidationRule
{
    /**
     * A completed set must have an effort_value greater than 0.
     * This applies universally: both repetitions and duration must be > 0 to mark complete.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $effortKey = str_replace('.is_completed', '.effort_value', $attribute);
        $effort = data_get(request()->all(), $effortKey);

        if ((int) $effort <= 0) {
            $fail(__('A set cannot be marked as completed with 0 effort.'));
        }
    }
}
