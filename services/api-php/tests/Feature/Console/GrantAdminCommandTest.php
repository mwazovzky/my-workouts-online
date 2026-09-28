<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GrantAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_grants_the_admin_role_to_a_verified_user(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('user:grant-admin', ['email' => 'owner@example.com'])
            ->expectsOutputToContain('owner@example.com is now an admin.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    #[Test]
    public function it_leaves_other_users_and_other_roles_untouched(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $user->roles()->attach(Role::where('name', 'User')->firstOrFail()->id);
        $other = User::factory()->create();

        $this->artisan('user:grant-admin', ['email' => 'owner@example.com'])->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('User'));
        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertFalse($other->fresh()->isAdmin());
    }

    #[Test]
    public function it_can_run_repeatedly(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('user:grant-admin', ['email' => 'owner@example.com'])->assertSuccessful();

        $this->artisan('user:grant-admin', ['email' => 'owner@example.com'])
            ->expectsOutputToContain('owner@example.com is already an admin.')
            ->assertSuccessful();

        $this->assertSame(1, $user->roles()->where('name', 'Admin')->count());
    }

    #[Test]
    public function it_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:grant-admin', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('No user is registered with the email nobody@example.com.')
            ->assertFailed();

        $this->assertDatabaseCount('role_user', 0);
    }

    #[Test]
    public function it_refuses_a_user_with_an_unverified_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);

        $this->artisan('user:grant-admin', ['email' => 'pending@example.com'])
            ->expectsOutputToContain('has not verified the email address')
            ->assertFailed();

        $this->assertFalse($user->fresh()->isAdmin());
    }

    #[Test]
    public function it_recreates_the_admin_role_when_it_is_missing(): void
    {
        Role::where('name', 'Admin')->delete();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('user:grant-admin', ['email' => 'owner@example.com'])->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }
}
