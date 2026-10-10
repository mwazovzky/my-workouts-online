<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * RFC 7386 merge patch for the current user: every field optional, at least one present.
 */
class MeUpdateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required_without_all:email,locale,theme_preference', 'filled', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'bail',
                'filled',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'locale' => ['sometimes', 'bail', 'filled', 'string', Rule::in(array_keys(config('app.available_locales', [])))],
            'theme_preference' => ['sometimes', 'bail', 'filled', 'string', 'in:light,dark,system'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locale' => __('Language'),
            'theme_preference' => __('Theme'),
        ];
    }
}
