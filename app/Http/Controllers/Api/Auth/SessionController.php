<?php

namespace App\Http\Controllers\Api\Auth;

use App\Auth\JwtUserResolver;
use App\Enums\ClientKind;
use App\Exceptions\Auth\RefreshTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TokenPair;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class SessionController extends Controller
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}

    /**
     * Exchange credentials for a token pair.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticateUser();
        $pair = $this->tokens->issuePair($user, $request->input('device_name'));

        Log::info('auth.login', ['user_id' => $user->id, 'client' => $request->client()->value]);

        return $this->tokenResponse($user, $pair, $request->client());
    }

    /**
     * Rotate the refresh token. Web clients present it via the httpOnly cookie
     * (guarded by a custom header), native clients in the body.
     */
    public function refresh(Request $request): JsonResponse
    {
        $bodyToken = $request->input('refresh_token');

        if (is_string($bodyToken) && $bodyToken !== '') {
            $client = ClientKind::Native;
            $plainToken = $bodyToken;
        } else {
            abort_unless(
                $request->header('X-Requested-With') === 'XMLHttpRequest',
                403,
                'Missing X-Requested-With header.',
            );

            $client = ClientKind::Web;
            $plainToken = $request->cookie(config('jwt.refresh_cookie.name'));
        }

        if (! is_string($plainToken) || $plainToken === '') {
            throw RefreshTokenException::missing();
        }

        $pair = $this->tokens->refresh($plainToken);
        $user = User::query()->findOrFail($this->userIdFromPair($pair));

        return $this->tokenResponse($user, $pair, $client);
    }

    /**
     * Revoke the refresh-token family behind the current access token.
     */
    public function logout(Request $request): Response
    {
        $claims = JwtUserResolver::claimsFrom($request);

        if ($claims) {
            $this->tokens->revokeFamily($claims->familyId);

            Log::info('auth.logout', ['user_id' => $claims->userId]);
        }

        return response()->noContent()->withCookie($this->forgetRefreshCookie());
    }

    private function tokenResponse(User $user, TokenPair $pair, ClientKind $client): JsonResponse
    {
        $payload = [
            'access_token' => $pair->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn,
            'user' => (new UserResource($user->loadMissing('roles')))->resolve(),
        ];

        if ($client === ClientKind::Native) {
            $payload['refresh_token'] = $pair->refreshToken;

            return response()->json($payload);
        }

        return response()->json($payload)->withCookie($this->refreshCookie($pair));
    }

    private function refreshCookie(TokenPair $pair): SymfonyCookie
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

    private function forgetRefreshCookie(): SymfonyCookie
    {
        return Cookie::forget(config('jwt.refresh_cookie.name'), config('jwt.refresh_cookie.path'));
    }

    private function userIdFromPair(TokenPair $pair): int
    {
        return $this->tokens->parseAccessToken($pair->accessToken)->userId;
    }
}
