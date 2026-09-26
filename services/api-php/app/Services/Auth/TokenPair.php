<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public string $refreshToken,
        public CarbonImmutable $refreshExpiresAt,
    ) {}
}
