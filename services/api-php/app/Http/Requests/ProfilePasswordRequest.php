<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class ProfilePasswordRequest extends ApiFormRequest
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
            'current_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }
}
