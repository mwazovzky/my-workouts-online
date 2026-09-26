<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function has_role_returns_true_when_role_assigned(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Editor']);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasRole('Editor'));
        $this->assertFalse($user->hasRole('Admin'));
    }

    #[Test]
    public function is_admin_returns_true_only_for_admin_role(): void
    {
        $admin = User::factory()->admin()->create();
        $regular = User::factory()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($regular->isAdmin());
    }

    #[Test]
    public function user_resource_exposes_is_admin_flag(): void
    {
        $admin = User::factory()->admin()->create();

        $array = (new \App\Http\Resources\UserResource($admin))->resolve();

        $this->assertTrue($array['is_admin']);
    }
}
