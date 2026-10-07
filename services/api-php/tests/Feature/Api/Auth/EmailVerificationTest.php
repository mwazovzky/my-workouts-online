<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Send the verification email and return the link parameters the SPA would post back.
     *
     * @return array{id: int, hash: string, expires: int, signature: string, url: string}
     */
    private function linkFor(User $user): array
    {
        Notification::fake();
        $user->sendEmailVerificationNotification();

        $params = [];
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$params) {
            $url = $notification->toMail($user)->actionUrl;
            $parts = parse_url($url);
            parse_str($parts['query'], $query);
            [, , $id, $hash] = explode('/', $parts['path']);

            $params = [
                'id' => (int) $id,
                'hash' => $hash,
                'expires' => (int) $query['expires'],
                'signature' => $query['signature'],
                'url' => $url,
            ];

            return true;
        });

        return $params;
    }

    private function token(User $user): string
    {
        return app(TokenServiceInterface::class)->issuePair($user)->accessToken;
    }

    #[Test]
    public function verification_link_points_at_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);
        $user = User::factory()->unverified()->create();

        $link = $this->linkFor($user);

        $this->assertStringStartsWith("https://app.example.test/verify-email/{$user->id}/".sha1($user->email).'?expires=', $link['url']);
    }

    #[Test]
    public function posting_the_link_parameters_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);
        Event::fake([Verified::class]);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/auth/email/verify', Arr::except($link, 'url'))
            ->assertNoContent();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);

        $this->withToken($this->token($user))->getJson('/api/v1/programs')->assertOk();
    }

    #[Test]
    public function link_for_another_account_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $link = $this->linkFor($other);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/auth/email/verify', Arr::except($link, 'url'))
            ->assertForbidden()
            ->assertJsonPath('code', 'invalid_verification_link');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function tampered_or_expired_signature_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/auth/email/verify', array_merge(Arr::except($link, 'url'), ['signature' => strrev($link['signature'])]))
            ->assertForbidden()
            ->assertJsonPath('code', 'invalid_signature');

        $this->travel(2)->hours();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/auth/email/verify', Arr::except($link, 'url'))
            ->assertForbidden()
            ->assertJsonPath('code', 'invalid_signature');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function verify_is_idempotent_for_verified_users(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);
        $user->markEmailAsVerified();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/auth/email/verify', Arr::except($link, 'url'))
            ->assertNoContent();
    }

    #[Test]
    public function resend_sends_for_unverified_and_noops_for_verified(): void
    {
        Notification::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->withToken($this->token($unverified))->postJson('/api/v1/auth/email/resend')->assertStatus(202);
        Notification::assertSentTo($unverified, VerifyEmail::class);

        $this->withToken($this->token($verified))->postJson('/api/v1/auth/email/resend')->assertNoContent();
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    #[Test]
    public function verify_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/verify', ['id' => 1, 'hash' => 'x', 'expires' => 1, 'signature' => 'y'])
            ->assertUnauthorized();
    }
}
