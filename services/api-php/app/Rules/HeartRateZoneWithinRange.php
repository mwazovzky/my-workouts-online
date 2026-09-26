<?php

namespace App\Rules;

use App\Enums\DifficultyUnit;
use App\Models\Exercise;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HeartRateZoneWithinRange implements ValidationRule
{
    /**
     * Per-request memo of exercise id => difficulty unit. The same rule
     * instance is reused across every set in one validation pass, so this
     * collapses what would be a per-set query into one query per exercise.
     *
     * @var array<int, ?DifficultyUnit>
     */
    private array $unitCache = [];

    /**
     * When the set's exercise uses the heart-rate-zone difficulty unit, the
     * difficulty value must be an integer between 1 and 5. Null (no target
     * zone, e.g. a drill) and non-zone units are left untouched.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $exerciseKey = preg_replace('/\.sets\.\d+\.difficulty_value$/', '.exercise_id', $attribute);
        $exerciseId = data_get(request()->all(), $exerciseKey);

        if (! $exerciseId) {
            return;
        }

        if ($this->unitFor((int) $exerciseId) !== DifficultyUnit::HeartRateZone) {
            return;
        }

        if (! is_numeric($value) || (int) $value != $value || $value < 1 || $value > 5) {
            $fail(__('Heart-rate zone must be between 1 and 5.'));
        }
    }

    private function unitFor(int $exerciseId): ?DifficultyUnit
    {
        if (! array_key_exists($exerciseId, $this->unitCache)) {
            // withTrashed so retired exercises are still zone-validated.
            $exercise = Exercise::withTrashed()->with('equipment')->find($exerciseId);
            $this->unitCache[$exerciseId] = $exercise?->equipment?->difficulty_unit;
        }

        return $this->unitCache[$exerciseId];
    }
}
