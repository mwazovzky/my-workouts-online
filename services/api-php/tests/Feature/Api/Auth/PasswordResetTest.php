<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function forgot_sends_a_reset_link_pointing_at_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertStatus(202);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith("https://app.example.test/reset-password/{$notification->token}?email=", $url);

            return true;
        });
    }

    #[Test]
    public function forgot_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])->assertStatus(202);

        Notification::assertNothingSent();
    }

    #[Test]
    public function forgot_swallows_mail_failures(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new RuntimeException('SMTP failure'));

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'user@example.com'])->assertStatus(202);
    }

    #[Test]
    public function reset_updates_the_password_and_revokes_every_device(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $tokens = app(TokenServiceInterface::class);
        $pair = $tokens->issuePair($user, 'phone');

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $pair) {
            $this->postJson('/api/v1/auth/password/reset', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertNoContent();

            $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
            $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $pair->refreshToken])->assertUnauthorized();

            return true;
        });
    }

    #[Test]
    public function reset_with_invalid_token_fails(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'bogus',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
