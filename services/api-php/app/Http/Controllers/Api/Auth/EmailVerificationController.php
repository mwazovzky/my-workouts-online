<?php

namespace App\Http\Controllers\Api\Auth;

use App\Auth\EmailVerificationLink;
use App\Enums\ErrorCode;
use App\Exceptions\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyEmailRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Mark the email verified from the parameters of the emailed link.
     */
    public function verify(VerifyEmailRequest $request): Response|JsonResponse
    {
        $user = $request->user();

        if ((int) $request->validated('id') !== $user->getKey()
            || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->validated('hash'))) {
            return ApiErrorResponse::problem(
                403,
                ErrorCode::InvalidVerificationLink,
                'This verification link does not belong to the signed-in account.',
            );
        }

        if (! EmailVerificationLink::isValid(
            (int) $request->validated('id'),
            (string) $request->validated('hash'),
            (int) $request->validated('expires'),
            (string) $request->validated('signature'),
        )) {
            return ApiErrorResponse::problem(
                403,
                ErrorCode::InvalidSignature,
                'This verification link is invalid or has expired.',
            );
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));

            Log::info('user.email_verified', ['user_id' => $user->id]);
        }

        return response()->noContent();
    }

    /**
     * Resend the verification email; 204 when nothing needs verifying.
     */
    public function resend(Request $request): Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->noContent();
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->noContent(202);
    }
}
