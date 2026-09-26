<?php

namespace App\Http\Controllers\Api\Auth;

use App\Auth\JwtUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ReauthRequest;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Step-up authentication: prove the password again and receive an access
 * token with a fresh `auth_time`, which sensitive actions can require.
 */
class ReauthController extends Controller
{
    public function __construct(private readonly TokenServiceInterface $tokens) {}

    public function __invoke(ReauthRequest $request): JsonResponse
    {
        $claims = JwtUserResolver::claimsFrom($request);

        abort_unless($claims, 403, 'Re-authentication requires a bearer access token.');

        $user = $request->user();

        if (! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        return response()->json([
            'access_token' => $this->tokens->stepUp($user, $claims->familyId),
            'expires_in' => (int) config('jwt.access_ttl'),
        ]);
    }
}
