<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasTranslationRules;
use App\Http\Requests\ApiFormRequest;

class CategoryStoreRequest extends ApiFormRequest
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
        return $this->translationRules(['name']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->translationMessages();
    }
}
