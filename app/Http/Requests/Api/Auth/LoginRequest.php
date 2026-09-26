<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\ClientKind;
use App\Http\Requests\Auth\LoginRequest as WebLoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Credential check for token issuance. Reuses the web request's throttling
 * but never touches the session guard.
 */
class LoginRequest extends WebLoginRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client' => ['required', Rule::enum(ClientKind::class)],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validate the credentials and return the user without starting a session.
     *
     * @throws ValidationException
     */
    public function authenticateUser(): User
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::createUserProvider('users');
        $user = $provider->retrieveByCredentials($this->only('email'));

        if (! $user instanceof User || ! $provider->validateCredentials($user, $this->only('password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function client(): ClientKind
    {
        return ClientKind::from($this->string('client')->toString());
    }
}
