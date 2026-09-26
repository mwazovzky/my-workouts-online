<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CategoryStoreRequest extends FormRequest
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
            'translations.ru' => ['sometimes', 'array'],
            'translations.ru.name' => ['nullable', 'string', 'max:255'],
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
