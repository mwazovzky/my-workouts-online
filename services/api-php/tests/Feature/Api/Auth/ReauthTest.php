<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReauthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function correct_password_returns_a_token_with_fresh_auth_time(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $tokens = app(TokenServiceInterface::class);
        $pair = $tokens->issuePair($user);
        $originalAuthTime = $tokens->parseAccessToken($pair->accessToken)->authenticatedAt;

        $this->travel(30)->minutes();
        $pair = $tokens->refresh($pair->refreshToken);
        $this->travel(5)->minutes();

        $response = $this->withToken($pair->accessToken)->postJson('/api/v1/auth/reauth', ['password' => 'secret123']);

        $response->assertOk()->assertJsonStructure(['access_token', 'expires_in']);

        $fresh = $tokens->parseAccessToken($response->json('access_token'));
        $this->assertTrue($fresh->authenticatedAt->greaterThan($originalAuthTime));
        $this->assertTrue($fresh->authenticatedAt->equalTo(now()->toImmutable()->startOfSecond()));

        $refreshedAgain = $tokens->refresh($pair->refreshToken);
        $this->assertTrue($tokens->parseAccessToken($refreshedAgain->accessToken)->authenticatedAt->equalTo($fresh->authenticatedAt));
    }

    #[Test]
    public function wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $pair = app(TokenServiceInterface::class)->issuePair($user);

        $this->withToken($pair->accessToken)->postJson('/api/v1/auth/reauth', ['password' => 'nope'])
            ->assertUnprocessable()
            ->assertFieldErrors(['password']);
    }

    #[Test]
    public function acting_as_without_a_real_bearer_token_cannot_step_up(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)->postJson('/api/v1/auth/reauth', ['password' => 'secret123'])->assertForbidden();
    }
}
