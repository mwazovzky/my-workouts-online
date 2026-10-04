<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'client' => 'native',
        ], $overrides);
    }

    #[Test]
    public function native_client_registers_and_receives_tokens(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->payload(['device_name' => 'iPhone']));

        $response->assertCreated()
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'user' => ['id', 'email']])
            ->assertJsonPath('user.email', 'new@example.com');

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id, 'device_name' => 'iPhone']);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function web_client_receives_refresh_cookie(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->payload(['client' => 'web']));

        $response->assertCreated()->assertJsonMissingPath('refresh_token');
        $this->assertNotNull($response->getCookie('refresh_token', false));
    }

    #[Test]
    public function unverified_user_is_blocked_from_verified_routes_with_a_code(): void
    {
        Notification::fake();

        $token = $this->postJson('/api/v1/auth/register', $this->payload())->json('access_token');

        $this->withToken($token)->getJson('/api/v1/programs')
            ->assertForbidden()
            ->assertJsonPath('code', 'email_unverified');

        $this->withToken($token)->patchJson('/api/v1/profile', ['name' => 'Still Me', 'email' => 'new@example.com'])
            ->assertOk();
    }

    #[Test]
    public function validates_input(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'client']);

        $this->postJson('/api/v1/auth/register', $this->payload(['password_confirmation' => 'other']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'Upper@Example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function rejects_a_non_string_email_with_a_validation_error_not_a_crash(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['email' => [['nested' => 1]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    #[Test]
    public function rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'new@example.com']);

        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
