<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TokenApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function issues_a_token_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'My Phone',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['token']);
    }

    #[Test]
    public function rejects_invalid_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'My Phone',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function rejects_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/tokens', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    #[Test]
    public function rate_limits_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/tokens', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_name' => 'My Phone',
            ])->assertUnprocessable();
        }

        $response = $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'My Phone',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->deleteJson('/api/v1/tokens/current');

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function revoke_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/v1/tokens/current');

        $response->assertUnauthorized();
    }
}
