<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RoleSeeder::class,
            CategorySeeder::class,
            EquipmentSeeder::class,
            ExerciseSeeder::class,
            WorkoutTemplateSeeder::class,
            ProgramSeeder::class,
            WorkoutSeeder::class,
        ]);
    }
}
