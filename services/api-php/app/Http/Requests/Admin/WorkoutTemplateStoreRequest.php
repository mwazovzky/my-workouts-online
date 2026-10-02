<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasTranslationRules;
use App\Rules\HeartRateZoneWithinRange;
use Illuminate\Foundation\Http\FormRequest;

class WorkoutTemplateStoreRequest extends FormRequest
{
    use HasTranslationRules;

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
            ...$this->translationRules(['name', 'description']),

            'activities' => ['required', 'array', 'min:1'],
            'activities.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'activities.*.order' => ['required', 'integer', 'min:1', 'max:1000'],
            'activities.*.sets' => ['required', 'array', 'min:1'],
            'activities.*.sets.*.order' => ['required', 'integer', 'min:1', 'max:1000'],
            'activities.*.sets.*.effort_value' => ['required', 'integer', 'min:0', 'max:100000'],
            'activities.*.sets.*.difficulty_value' => ['nullable', 'numeric', 'min:0', 'max:10000', new HeartRateZoneWithinRange],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->translationMessages(),
            'activities.min' => __('A workout template must have at least one activity.'),
            'activities.*.sets.min' => __('Each activity must have at least one set.'),
        ];
    }
}
