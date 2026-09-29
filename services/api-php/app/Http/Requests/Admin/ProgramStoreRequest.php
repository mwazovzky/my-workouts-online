<?php

namespace App\Http\Requests\Admin;

use App\Enums\Weekday;
use App\Http\Requests\Admin\Concerns\HasTranslationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramStoreRequest extends FormRequest
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

            'assignments' => ['present', 'array'],
            'assignments.*.workout_template_id' => ['required', 'integer', 'exists:workout_templates,id'],
            'assignments.*.weekday' => ['required', Rule::in(Weekday::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->translationMessages();
    }
}
