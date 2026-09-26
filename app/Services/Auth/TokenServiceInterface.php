<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AccessTokenException;
use App\Exceptions\Auth\RefreshTokenException;
use App\Models\User;

interface TokenServiceInterface
{
    /**
     * Start a new refresh-token family for the user and issue the first pair.
     */
    public function issuePair(User $user, ?string $deviceName = null): TokenPair;

    /**
     * Rotate a refresh token: the old one becomes unusable, a new pair is issued
     * in the same family. Reuse of an already rotated token revokes the family.
     *
     * @throws RefreshTokenException
     */
    public function refresh(string $plainRefreshToken): TokenPair;

    /**
     * Revoke every refresh token in a family (one device / login).
     */
    public function revokeFamily(string $familyId): void;

    /**
     * Revoke every refresh token of the user, optionally sparing one family.
     */
    public function revokeAllForUser(User $user, ?string $exceptFamilyId = null): void;

    /**
     * Re-authentication: refresh the family's `auth_time` and return a new
     * access token carrying it, so step-up checks pass.
     */
    public function stepUp(User $user, string $familyId): string;

    /**
     * Verify signature, issuer, audience and expiry of an access token.
     *
     * @throws AccessTokenException
     */
    public function parseAccessToken(string $jwt): AccessTokenClaims;

    /**
     * Public verification key set for /.well-known/jwks.json.
     *
     * @return array{keys: list<array<string, string>>}
     */
    public function jwks(): array;
}
