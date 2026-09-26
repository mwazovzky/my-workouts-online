<?php

namespace App\Http\Requests\Admin;

use App\Enums\EffortType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseStoreRequest extends FormRequest
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
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'effort_type' => ['required', Rule::enum(EffortType::class)],
            'rest_time_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'translations' => ['required', 'array'],
            'translations.en' => ['required', 'array'],
            'translations.en.name' => ['required', 'string', 'max:255'],
            'translations.en.description' => ['nullable', 'string', 'max:2000'],
            'translations.ru' => ['sometimes', 'array'],
            'translations.ru.name' => ['nullable', 'string', 'max:255'],
            'translations.ru.description' => ['nullable', 'string', 'max:2000'],
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
