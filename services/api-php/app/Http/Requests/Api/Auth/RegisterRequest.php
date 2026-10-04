<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\ClientKind;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'client' => ['required', Rule::enum(ClientKind::class)],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function client(): ClientKind
    {
        return ClientKind::from($this->string('client')->toString());
    }
}
