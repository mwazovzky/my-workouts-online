<?php

namespace Tests\Feature\Api\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\TokenPair;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshTest extends TestCase
{
    use RefreshDatabase;

    private function issue(User $user): TokenPair
    {
        return app(TokenServiceInterface::class)->issuePair($user, 'test');
    }

    #[Test]
    public function native_client_rotates_refresh_token(): void
    {
        $user = User::factory()->create();
        $pair = $this->issue($user);

        $response = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'user'])
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotSame($pair->refreshToken, $response->json('refresh_token'));
        $this->assertDatabaseCount('refresh_tokens', 2);

        $old = RefreshToken::query()->where('token_hash', RefreshToken::hashToken($pair->refreshToken))->first();
        $this->assertTrue($old->isRotated());
        $this->assertSame($old->family_id, $old->replacedBy->family_id);
    }

    #[Test]
    public function web_client_rotates_via_cookie_with_csrf_header(): void
    {
        $user = User::factory()->create();
        $pair = $this->issue($user);

        $response = $this
            ->withCredentials()
            ->withUnencryptedCookie('refresh_token', $pair->refreshToken)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk()->assertJsonMissingPath('refresh_token');

        $cookie = $response->getCookie('refresh_token', false);
        $this->assertNotNull($cookie);
        $this->assertNotSame($pair->refreshToken, $cookie->getValue());
    }

    #[Test]
    public function web_client_without_csrf_header_is_refused(): void
    {
        $user = User::factory()->create();
        $pair = $this->issue($user);

        $this->withCredentials()
            ->withUnencryptedCookie('refresh_token', $pair->refreshToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden();
    }

    #[Test]
    public function reusing_a_rotated_token_revokes_the_whole_family(): void
    {
        $user = User::factory()->create();
        $pair = $this->issue($user);

        $newToken = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])
            ->json('refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'refresh_token_reused');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $newToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'refresh_token_invalid');

        $this->assertSame(0, RefreshToken::query()->whereNull('revoked_at')->count());
    }

    #[Test]
    public function expired_refresh_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $pair = $this->issue($user);

        $this->travel(31)->days();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'refresh_token_expired');
    }

    #[Test]
    public function unknown_and_missing_tokens_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'nope'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'refresh_token_invalid');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'refresh_token_missing');
    }

    #[Test]
    public function refresh_preserves_the_original_authentication_time(): void
    {
        $user = User::factory()->create();
        $pair = $this->issue($user);
        $tokens = app(TokenServiceInterface::class);

        $authTime = $tokens->parseAccessToken($pair->accessToken)->authenticatedAt;

        $this->travel(10)->minutes();

        $refreshed = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])
            ->json('access_token');

        $this->assertTrue($authTime->equalTo($tokens->parseAccessToken($refreshed)->authenticatedAt));
    }
}
