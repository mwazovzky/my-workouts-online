<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'John Doe', 'email' => 'test@example.com', 'password' => bcrypt('password')],
        ];

        foreach ($users as $user) {
            User::factory()->create($user);
        }
    }
}
