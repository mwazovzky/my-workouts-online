<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returns_the_bearer_user(): void
    {
        $user = User::factory()->create(['locale' => 'ru', 'theme_preference' => 'dark']);
        $token = app(TokenServiceInterface::class)->issuePair($user)->accessToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'ru',
                'theme_preference' => 'dark',
                'is_admin' => false,
                'email_verified' => true,
            ]]);
    }

    #[Test]
    public function reports_admin_role_and_unverified_email(): void
    {
        $admin = User::factory()->admin()->unverified()->create();

        $this->actingAs($admin)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.email_verified', false);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
