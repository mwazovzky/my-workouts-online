<?php

namespace App\Http\Requests\Admin;

use App\Enums\DifficultyUnit;
use App\Http\Requests\Admin\Concerns\HasTranslationRules;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class EquipmentStoreRequest extends ApiFormRequest
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
            'difficulty_unit' => ['required', Rule::enum(DifficultyUnit::class)],
            ...$this->translationRules(['name']),
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
