<?php

namespace Tests\Feature\Api\Auth;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JwtKeysCommandTest extends TestCase
{
    #[Test]
    public function prints_a_fresh_key_pair(): void
    {
        $this->artisan('jwt:keys')
            ->expectsOutputToContain('JWT_PRIVATE_KEY=')
            ->expectsOutputToContain('JWT_PUBLIC_KEY=')
            ->assertSuccessful();
    }
}
