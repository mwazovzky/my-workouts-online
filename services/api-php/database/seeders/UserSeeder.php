<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed the demo admin account used for local development and contract tests.
     *
     * @throws RuntimeException
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UserSeeder creates a demo admin with a known password and must not run in production.');
        }

        $user = User::updateOrCreate(
            [
                'email' => 'test@example.com',
            ],
            [
                'name' => 'John Doe',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $adminRole = Role::where('name', 'Admin')->first();

        if ($adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
