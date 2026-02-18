<?php

namespace App\Http\Requests;

use App\Rules\CompletedSetRequiresEffort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkoutSaveRequest extends FormRequest
{
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
                'integer',
                'distinct',
                Rule::exists('activities', 'id')
                    ->where('workout_type', 'workout')
                    ->where('workout_id', $workoutId),
            ],
            'activities.*.exercise_id' => ['required', 'integer', Rule::exists('exercises', 'id')],
            'activities.*.order' => ['required', 'integer', 'min:1', 'distinct'],

            'activities.*.sets' => ['required', 'array', 'min:1'],
            'activities.*.sets.*.id' => ['nullable', 'integer', 'distinct'],
            'activities.*.sets.*.order' => ['required', 'integer', 'min:1'],
            'activities.*.sets.*.effort_value' => ['required', 'integer', 'min:0'],
            'activities.*.sets.*.difficulty_value' => ['nullable', 'numeric', 'min:0'],
            'activities.*.sets.*.is_completed' => ['sometimes', 'boolean', new CompletedSetRequiresEffort],
        ];
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
