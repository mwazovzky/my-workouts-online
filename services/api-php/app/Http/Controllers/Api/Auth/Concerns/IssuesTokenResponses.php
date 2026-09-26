<?php

namespace App\Http\Controllers\Api\Auth\Concerns;

use App\Enums\ClientKind;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TokenPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

trait IssuesTokenResponses
{
    /**
     * Web clients get the refresh token as an httpOnly cookie, native clients in the body.
     */
    protected function tokenResponse(User $user, TokenPair $pair, ClientKind $client, int $status = 200): JsonResponse
    {
        $payload = [
            'access_token' => $pair->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn,
            'user' => (new UserResource($user->loadMissing('roles')))->resolve(),
        ];

        if ($client === ClientKind::Native) {
            $payload['refresh_token'] = $pair->refreshToken;

            return response()->json($payload, $status);
        }

        return response()->json($payload, $status)->withCookie($this->refreshCookie($pair));
    }

    protected function refreshCookie(TokenPair $pair): SymfonyCookie
    {
        return Cookie::make(
            name: config('jwt.refresh_cookie.name'),
            value: $pair->refreshToken,
            minutes: (int) ceil(config('jwt.refresh_ttl') / 60),
            path: config('jwt.refresh_cookie.path'),
            secure: config('jwt.refresh_cookie.secure'),
            httpOnly: true,
            sameSite: 'strict',
        );
    }

    protected function forgetRefreshCookie(): SymfonyCookie
    {
        return Cookie::forget(config('jwt.refresh_cookie.name'), config('jwt.refresh_cookie.path'));
    }
}
