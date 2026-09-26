<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharedAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shared_auth_user_exposes_is_admin_true_for_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.is_admin', true)
                ->where('auth.user.id', $admin->id)
            );
    }

    #[Test]
    public function shared_auth_user_exposes_is_admin_false_for_regular_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.is_admin', false)
            );
    }
}
