<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\ApiFormRequest;

class RefreshRequest extends ApiFormRequest
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
            'refresh_token' => ['nullable', 'string'],
        ];
    }
}
