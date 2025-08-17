<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = [
            ['name' => 'Barbell', 'unit' => 'kg'],
            ['name' => 'Dumbbell', 'unit' => 'kg'],
            ['name' => 'Kettlebell', 'unit' => 'kg'],
            ['name' => 'Smith Machine', 'unit' => 'machine unit'],
            ['name' => 'Cable Machine', 'unit' => 'machine unit'],
            ['name' => 'Leg Press Machine', 'unit' => 'machine unit'],
            ['name' => 'Chest Press Machine', 'unit' => 'machine unit'],
            ['name' => 'Seated Row Machine', 'unit' => 'machine unit'],
            ['name' => 'Lat Pulldown Machine', 'unit' => 'machine unit'],
            ['name' => 'Leg Extension Machine', 'unit' => 'machine unit'],
            ['name' => 'Leg Curl Machine', 'unit' => 'machine unit'],
            ['name' => 'Calf Raise Machine', 'unit' => 'machine unit'],
            ['name' => 'Bench', 'unit' => 'none'],
            ['name' => 'Pull-up Bar', 'unit' => 'none'],
            ['name' => 'Treadmill', 'unit' => 'none'],
        ];

        foreach ($equipment as $item) {
            Equipment::create($item);
        }
    }
}
