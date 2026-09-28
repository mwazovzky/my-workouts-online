<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The application depends on these roles by name (User::isAdmin() checks
 * "Admin"), so every environment needs them, including ones that never seed.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $roles = [
        'Admin' => 'Administrator role with full access',
        'User' => 'Regular user role with limited access',
    ];

    public function up(): void
    {
        foreach ($this->roles as $name => $description) {
            if (DB::table('roles')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('roles')->insert([
                'name' => $name,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
