<?php

namespace App\Http\Requests;

class WorkoutStoreRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workout_template_id' => ['required', 'integer:strict', 'exists:workout_templates,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'workout_template_id.required' => __('A workout template is required to start a workout.'),
            'workout_template_id.exists' => __('The selected workout template could not be found.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'workout_template_id' => 'workout template',
        ];
    }
}
