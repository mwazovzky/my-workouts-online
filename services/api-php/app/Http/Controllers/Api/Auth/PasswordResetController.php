<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}

    /**
     * Email a reset link. Always accepted so the response does not reveal
     * whether the address exists.
     */
    public function forgot(ForgotPasswordRequest $request): Response
    {
        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->noContent(202);
    }

    /**
     * Set a new password from a reset token and revoke every device.
     */
    public function reset(ResetPasswordRequest $request): Response
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->validated('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                $this->tokens->revokeAllForUser($user);

                event(new PasswordReset($user));

                Log::info('auth.password_reset', ['user_id' => $user->id]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return response()->noContent();
    }
}
