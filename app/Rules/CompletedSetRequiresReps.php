<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CompletedSetRequiresReps implements ValidationRule
{
    /**
     * A completed set must have at least 1 repetition.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $repsKey = str_replace('.is_completed', '.repetitions', $attribute);
        $reps = data_get(request()->all(), $repsKey);

        if ((int) $reps <= 0) {
            $fail('A set cannot be marked as completed with 0 repetitions.');
        }
    }
}
