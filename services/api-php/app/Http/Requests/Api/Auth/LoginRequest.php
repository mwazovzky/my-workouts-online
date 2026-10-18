<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\ClientKind;
use App\Enums\ErrorCode;
use App\Exceptions\Api\ApiException;
use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Credential check for token issuance, throttled per email + IP. Malformed
 * input is a 422; wrong credentials are a 401 and a lockout a 429.
 */
class LoginRequest extends ApiFormRequest
{
    /**
     * Checked when no user has the email, so an unknown email costs one
     * bcrypt check like a wrong password and the timing reveals nothing.
     * Cost 12, production's BCRYPT_ROUNDS; the password is random and unknown.
     */
    private const DUMMY_HASH = '$2y$12$4vghJGebw9CUia09n0CJbuVUcIolO5uldAAGBsM0iiDPurjG7E9n.';

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'client' => ['required', Rule::enum(ClientKind::class)],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validate the credentials and return the user without starting a session.
     *
     * @throws ApiException 401 `invalid_credentials` or 429 `too_many_attempts`
     */
    public function authenticateUser(): User
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::createUserProvider('users');
        $user = $provider->retrieveByCredentials($this->only('email'));

        if (! $user instanceof User) {
            Hash::check($this->string('password')->toString(), self::DUMMY_HASH);
        }

        if (! $user instanceof User || ! $provider->validateCredentials($user, $this->only('password'))) {
            RateLimiter::hit($this->throttleKey());

            throw new ApiException(401, ErrorCode::InvalidCredentials, trans('auth.failed'));
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function client(): ClientKind
    {
        return ClientKind::from($this->string('client')->toString());
    }

    /**
     * @throws ApiException 429 `too_many_attempts` with `Retry-After`
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw new ApiException(429, ErrorCode::TooManyAttempts, trans('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => ceil($seconds / 60),
        ]), ['Retry-After' => (string) $seconds]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
