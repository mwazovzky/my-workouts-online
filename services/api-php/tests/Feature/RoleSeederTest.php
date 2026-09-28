<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the RoleSeeder seeds roles correctly.
     */
    #[Test]
    public function roles_are_seeded(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
        $this->assertDatabaseHas('roles', ['name' => 'User']);
    }

    #[Test]
    public function roles_exist_without_seeding(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
        $this->assertDatabaseHas('roles', ['name' => 'User']);
    }

    #[Test]
    public function the_seeder_can_run_repeatedly(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(1, Role::where('name', 'Admin')->count());
        $this->assertSame(1, Role::where('name', 'User')->count());
    }

    #[Test]
    public function the_seeder_restores_a_missing_role(): void
    {
        Role::where('name', 'User')->delete();

        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'User']);
    }
}
