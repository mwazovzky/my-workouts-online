<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;

final readonly class AccessTokenClaims
{
    public function __construct(
        public int $userId,
        public string $familyId,
        public string $tokenId,
        public CarbonImmutable $authenticatedAt,
        public CarbonImmutable $expiresAt,
    ) {}
}
