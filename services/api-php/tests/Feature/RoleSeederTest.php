<?php

namespace Tests\Feature;

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
}
