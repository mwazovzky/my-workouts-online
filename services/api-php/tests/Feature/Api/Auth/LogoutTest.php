<?php

namespace Tests\Feature\Api\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function logout_revokes_the_current_family_and_clears_the_cookie(): void
    {
        $user = User::factory()->create();
        $tokens = app(TokenServiceInterface::class);
        $current = $tokens->issuePair($user, 'laptop');
        $other = $tokens->issuePair($user, 'phone');

        $response = $this->withToken($current->accessToken)->postJson('/api/v1/auth/logout');

        $response->assertNoContent();

        $cookie = $response->getCookie('refresh_token', false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isCleared());

        $this->assertTrue(RefreshToken::query()->where('token_hash', RefreshToken::hashToken($current->refreshToken))->first()->isRevoked());
        $this->assertFalse(RefreshToken::query()->where('token_hash', RefreshToken::hashToken($other->refreshToken))->first()->isRevoked());

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $current->refreshToken])->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $other->refreshToken])->assertOk();
    }

    #[Test]
    public function logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }
}
