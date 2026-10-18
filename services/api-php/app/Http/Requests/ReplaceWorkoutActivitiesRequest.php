<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksActivityPayload;
use App\Models\Activity;
use App\Models\Set;
use App\Rules\CompletedSetRequiresEffort;
use App\Rules\HeartRateZoneWithinRange;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReplaceWorkoutActivitiesRequest extends ApiFormRequest
{
    use ChecksActivityPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workout = $this->route('workout');
        $workoutId = is_object($workout) ? $workout->getKey() : $workout;

        return [
            'activities' => ['required', 'array', 'min:1'],

            'activities.*.id' => [
                'nullable',
                'integer:strict',
                'distinct',
                Rule::exists('activities', 'id')
                    ->where('workout_type', 'workout')
                    ->where('workout_id', $workoutId),
            ],
            'activities.*.exercise_id' => ['required', 'integer:strict', Rule::exists('exercises', 'id')],
            'activities.*.order' => ['required', 'integer:strict', 'min:1', 'max:1000', 'distinct'],

            'activities.*.sets' => ['required', 'array', 'min:1'],
            'activities.*.sets.*.id' => ['nullable', 'integer:strict', 'distinct'],
            'activities.*.sets.*.order' => ['required', 'integer:strict', 'min:1', 'max:1000'],
            'activities.*.sets.*.effort_value' => ['required', 'integer:strict', 'min:0', 'max:100000'],
            'activities.*.sets.*.difficulty_value' => ['nullable', 'numeric', 'min:0', 'max:10000', new HeartRateZoneWithinRange],
            'activities.*.sets.*.is_completed' => ['sometimes', 'boolean', new CompletedSetRequiresEffort],
        ];
    }

    /**
     * Beyond the rules: an activity keeps a retired exercise only if it
     * already has it, set orders are unique per activity, and a set id must
     * be one of its own activity's sets.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $existing = $this->existingActivities();

            $this->checkActivities($validator, static fn (array $activity, int $exerciseId): bool => is_int($activity['id'] ?? null)
                && ($existing[$activity['id']]['exercise_id'] ?? null) === $exerciseId);

            foreach ($this->activitiesPayload() as $i => $activity) {
                $ownSets = is_int($activity['id'] ?? null) ? ($existing[$activity['id']]['set_ids'] ?? []) : [];

                foreach ($this->setsPayload($activity) as $j => $set) {
                    $setId = $set['id'] ?? null;

                    if (is_int($setId) && ! in_array($setId, $ownSets, true)) {
                        $validator->addFailure("activities.{$i}.sets.{$j}.id", 'Exists', ['sets', 'id']);
                    }
                }
            }
        });
    }

    /**
     * The workout's activities by id, with their exercise and set ids.
     *
     * @return array<int, array{exercise_id: int, set_ids: list<int>}>
     */
    private function existingActivities(): array
    {
        $workout = $this->route('workout');
        $activities = Activity::query()
            ->where('workout_type', 'workout')
            ->where('workout_id', is_object($workout) ? $workout->getKey() : $workout)
            ->pluck('exercise_id', 'id');

        $setIds = Set::query()->whereIn('activity_id', $activities->keys())->get(['id', 'activity_id'])
            ->groupBy('activity_id');

        return $activities->map(fn (int $exerciseId, int $id): array => [
            'exercise_id' => $exerciseId,
            'set_ids' => $setIds->get($id)?->pluck('id')->all() ?? [],
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activities.min' => __('A workout must have at least one activity.'),
            'activities.*.sets.min' => __('Each activity must have at least one set.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activities' => 'activities',
            'activities.*.exercise_id' => 'exercise',
            'activities.*.order' => 'activity order',
            'activities.*.sets' => 'sets',
            'activities.*.sets.*.order' => 'set order',
            'activities.*.sets.*.effort_value' => 'effort value',
            'activities.*.sets.*.difficulty_value' => 'difficulty value',
            'activities.*.sets.*.is_completed' => 'completion status',
        ];
    }
}
