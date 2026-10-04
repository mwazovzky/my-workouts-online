<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_verified_user_that_can_log_in(): void
    {
        $this->artisan('user:create', [
            'name' => 'App Review',
            'email' => 'review@example.com',
            'password' => 'review-password',
            '--verified' => true,
        ])
            ->expectsOutputToContain('review@example.com created (verified).')
            ->assertSuccessful();

        $user = User::where('email', 'review@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('review-password', $user->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'review@example.com',
            'password' => 'review-password',
            'client' => 'native',
        ])->assertOk();
    }

    #[Test]
    public function it_creates_an_unverified_user_by_default(): void
    {
        $this->artisan('user:create', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'long-enough'])
            ->assertSuccessful();

        $this->assertFalse(User::where('email', 'new@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    #[Test]
    public function it_rejects_a_duplicate_email_and_a_short_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('user:create', ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'long-enough'])
            ->assertFailed();

        $this->artisan('user:create', ['name' => 'X', 'email' => 'short@example.com', 'password' => 'short'])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }
}
