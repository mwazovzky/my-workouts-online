<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function valid_token_is_accepted(): void
    {
        $user = User::factory()->create();
        $pair = app(TokenServiceInterface::class)->issuePair($user);

        $this->withToken($pair->accessToken)->getJson('/api/v1/programs')->assertOk();
    }

    #[Test]
    public function expired_token_returns_token_expired_code(): void
    {
        $user = User::factory()->create();
        $pair = app(TokenServiceInterface::class)->issuePair($user);

        $this->travel(16)->minutes();

        $this->withToken($pair->accessToken)->getJson('/api/v1/programs')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'token_expired');
    }

    #[Test]
    public function token_within_leeway_is_still_accepted(): void
    {
        $user = User::factory()->create();
        $pair = app(TokenServiceInterface::class)->issuePair($user);

        $this->travelTo(now()->addMinutes(15)->addSeconds(10));

        $this->withToken($pair->accessToken)->getJson('/api/v1/programs')->assertOk();
    }

    #[Test]
    public function tampered_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $pair = app(TokenServiceInterface::class)->issuePair($user);

        [$header, $payload, $signature] = explode('.', $pair->accessToken);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $claims['sub'] = (string) ($user->id + 1);
        $forgedPayload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        $this->withToken("{$header}.{$forgedPayload}.{$signature}")->getJson('/api/v1/programs')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    #[Test]
    public function garbage_token_is_rejected(): void
    {
        $this->withToken('not-a-jwt')->getJson('/api/v1/programs')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    #[Test]
    public function token_for_deleted_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $pair = app(TokenServiceInterface::class)->issuePair($user);
        $user->delete();

        $this->withToken($pair->accessToken)->getJson('/api/v1/programs')->assertUnauthorized();
    }
}
