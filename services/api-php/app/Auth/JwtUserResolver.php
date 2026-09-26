<?php

namespace App\Auth;

use App\Exceptions\Auth\AccessTokenException;
use App\Models\User;
use App\Services\Auth\AccessTokenClaims;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Http\Request;

/**
 * Resolves the user for the `jwt` guard from a bearer access token.
 *
 * On failure it records the reason on the request so the 401 response can
 * carry a machine-readable code (`token_expired` vs `unauthenticated`).
 */
class JwtUserResolver
{
    public const CLAIMS_ATTRIBUTE = 'jwt.claims';

    public const ERROR_ATTRIBUTE = 'auth.error';

    public function __construct(private readonly TokenServiceInterface $tokens) {}

    public function __invoke(Request $request): ?User
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return null;
        }

        try {
            $claims = $this->tokens->parseAccessToken($bearer);
        } catch (AccessTokenException $e) {
            $request->attributes->set(self::ERROR_ATTRIBUTE, $e->errorCode);

            return null;
        }

        $user = User::query()->find($claims->userId);

        if (! $user) {
            return null;
        }

        $request->attributes->set(self::CLAIMS_ATTRIBUTE, $claims);

        return $user;
    }

    public static function claimsFrom(Request $request): ?AccessTokenClaims
    {
        $claims = $request->attributes->get(self::CLAIMS_ATTRIBUTE);

        return $claims instanceof AccessTokenClaims ? $claims : null;
    }
}
