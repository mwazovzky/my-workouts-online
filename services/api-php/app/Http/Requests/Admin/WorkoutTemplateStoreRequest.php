<?php

namespace App\Http\Requests\Admin;

use App\Rules\HeartRateZoneWithinRange;
use Illuminate\Foundation\Http\FormRequest;

class WorkoutTemplateStoreRequest extends FormRequest
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
        return [
            'translations' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.en.name' => ['required', 'string', 'max:255'],
            'translations.en.description' => ['nullable', 'string', 'max:2000'],
            'translations.ru' => ['sometimes', 'array'],
            'translations.ru.name' => ['nullable', 'string', 'max:255'],
            'translations.ru.description' => ['nullable', 'string', 'max:2000'],

            'activities' => ['required', 'array', 'min:1'],
            'activities.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'activities.*.order' => ['required', 'integer', 'min:1'],
            'activities.*.sets' => ['required', 'array', 'min:1'],
            'activities.*.sets.*.order' => ['required', 'integer', 'min:1'],
            'activities.*.sets.*.effort_value' => ['required', 'integer', 'min:0'],
            'activities.*.sets.*.difficulty_value' => ['nullable', 'numeric', 'min:0', new HeartRateZoneWithinRange],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'translations.en.name.required' => __('An English name is required.'),
            'activities.min' => __('A workout template must have at least one activity.'),
            'activities.*.sets.min' => __('Each activity must have at least one set.'),
        ];
    }
}
