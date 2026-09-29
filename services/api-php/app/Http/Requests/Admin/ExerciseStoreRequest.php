<?php

namespace App\Http\Requests\Admin;

use App\Enums\EffortType;
use App\Http\Requests\Admin\Concerns\HasTranslationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseStoreRequest extends FormRequest
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
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'effort_type' => ['required', Rule::enum(EffortType::class)],
            'rest_time_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            ...$this->translationRules(['name', 'description']),
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
