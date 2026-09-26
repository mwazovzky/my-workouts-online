<?php

namespace Tests\Feature\Api\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function web_client_receives_access_token_and_refresh_cookie(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'client' => 'web',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'email', 'is_admin']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('refresh_token');

        $cookie = $response->getCookie('refresh_token', false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/api/v1/auth/refresh', $cookie->getPath());
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'token_hash' => RefreshToken::hashToken($cookie->getValue()),
        ]);
    }

    #[Test]
    public function native_client_receives_refresh_token_in_body_and_no_cookie(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'client' => 'native',
            'device_name' => 'iPhone',
        ]);

        $response->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        $this->assertNull($response->getCookie('refresh_token', false));
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id, 'device_name' => 'iPhone']);
    }

    #[Test]
    public function access_token_authenticates_api_requests(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'client' => 'native',
        ])->json('access_token');

        $this->withToken($token)->getJson('/api/v1/programs')->assertOk();
    }

    #[Test]
    public function rejects_invalid_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'client' => 'web',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    #[Test]
    public function requires_client_kind(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.c', 'password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.c', 'password' => 'x', 'client' => 'desktop'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client']);
    }

    #[Test]
    public function login_does_not_start_a_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'client' => 'web',
        ])->assertOk();

        $this->assertGuest('web');
    }

    #[Test]
    public function rate_limits_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong',
                'client' => 'web',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'client' => 'web',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
