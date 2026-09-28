<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Roles and the catalog are reference data for every environment.
     * The demo user and demo workouts are never created in production.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CatalogSeeder::class,
        ]);

        if (app()->isProduction()) {
            return;
        }

        $this->call([
            UserSeeder::class,
            WorkoutSeeder::class,
        ]);
    }
}
