<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasTranslationRules;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ChecksActivityPayload;
use App\Models\WorkoutTemplate;
use App\Rules\HeartRateZoneWithinRange;
use Illuminate\Validation\Validator;

class WorkoutTemplateStoreRequest extends ApiFormRequest
{
    use ChecksActivityPayload;
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
            'activities.*.exercise_id' => ['required', 'integer:strict', 'exists:exercises,id'],
            'activities.*.order' => ['required', 'integer:strict', 'min:1', 'max:1000', 'distinct'],
            'activities.*.sets' => ['required', 'array', 'min:1'],
            'activities.*.sets.*.order' => ['required', 'integer:strict', 'min:1', 'max:1000'],
            'activities.*.sets.*.effort_value' => ['required', 'integer:strict', 'min:0', 'max:100000'],
            'activities.*.sets.*.difficulty_value' => ['nullable', 'numeric', 'min:0', 'max:10000', new HeartRateZoneWithinRange],
        ];
    }

    /**
     * Beyond the rules: a retired exercise may stay where the template
     * already uses it but not be added, and set orders are unique per activity.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $template = $this->route('workout_template');
            $kept = $template instanceof WorkoutTemplate
                ? array_flip($template->activities()->pluck('exercise_id')->all())
                : [];

            $this->checkActivities($validator, static fn (array $activity, int $exerciseId): bool => isset($kept[$exerciseId]));
        });
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
