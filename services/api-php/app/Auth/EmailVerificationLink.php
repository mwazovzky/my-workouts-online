<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Builds and validates email-verification links.
 *
 * The link is signed against the named `verification.verify` route (kept for
 * URL generation only); the host is swapped for the frontend URL so the SPA
 * owns the page and posts the parameters to the API.
 */
class EmailVerificationLink
{
    public static function forUser(MustVerifyEmail $user): string
    {
        $signed = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        );

        return str_replace(
            rtrim(config('app.url'), '/'),
            rtrim(config('app.frontend_url'), '/'),
            $signed,
        );
    }

    public static function isValid(int $id, string $hash, int $expires, string $signature): bool
    {
        $url = URL::route('verification.verify', ['id' => $id, 'hash' => $hash, 'expires' => $expires]);

        return URL::hasValidSignature(Request::create($url.'&signature='.$signature));
    }
}
