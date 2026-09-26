<?php

namespace App\Http\Requests\Admin;

use App\Enums\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramStoreRequest extends FormRequest
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
        return [
            'translations.en.name.required' => __('An English name is required.'),
        ];
    }
}
