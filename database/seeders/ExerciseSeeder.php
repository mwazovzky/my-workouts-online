<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            ['name' => 'Bench Press', 'equipment' => 'Barbell', 'description' => 'Chest strength exercise using a barbell.', 'categories' => ['Chest', 'Triceps']],
            ['name' => 'Squat', 'equipment' => 'Barbell', 'description' => 'Leg strength exercise using a barbell.', 'categories' => ['Legs']],
            ['name' => 'Deadlift', 'equipment' => 'Barbell', 'description' => 'Full body strength exercise using a barbell.', 'categories' => ['Back', 'Legs']],
            ['name' => 'Dumbbell Curl', 'equipment' => 'Dumbbell', 'description' => 'Biceps exercise using dumbbells.', 'categories' => ['Biceps']],
            ['name' => 'Triceps Extension', 'equipment' => 'Dumbbell', 'description' => 'Triceps exercise using dumbbells.', 'categories' => ['Triceps']],
            ['name' => 'Lat Pulldown', 'equipment' => 'Lat Pulldown Machine', 'description' => 'Back exercise using lat pulldown machine.', 'categories' => ['Back']],
            ['name' => 'Leg Press', 'equipment' => 'Leg Press Machine', 'description' => 'Leg exercise using leg press machine.', 'categories' => ['Legs']],
            ['name' => 'Chest Press', 'equipment' => 'Chest Press Machine', 'description' => 'Chest exercise using chest press machine.', 'categories' => ['Chest']],
            ['name' => 'Pull-up', 'equipment' => 'Pull-up Bar', 'description' => 'Back and arms exercise using pull-up bar.', 'categories' => ['Back', 'Biceps']],
            ['name' => 'Kettlebell Swing', 'equipment' => 'Kettlebell', 'description' => 'Full body exercise using kettlebell.', 'categories' => ['Legs', 'Back', 'Shoulders']],
            ['name' => 'Incline Dumbbell Press', 'equipment' => 'Dumbbell', 'description' => 'Incline chest exercise using dumbbells.', 'categories' => ['Chest', 'Shoulders']],
            ['name' => 'Lateral Raise', 'equipment' => 'Dumbbell', 'description' => 'Shoulder isolation exercise.', 'categories' => ['Shoulders']],
            ['name' => 'Chest Fly', 'equipment' => 'Chest Press Machine', 'description' => 'Chest isolation exercise using chest press machine.', 'categories' => ['Chest']],
            ['name' => 'Seated Row', 'equipment' => 'Seated Row Machine', 'description' => 'Back exercise using seated row machine.', 'categories' => ['Back']],
            ['name' => 'Hammer Curl', 'equipment' => 'Dumbbell', 'description' => 'Biceps exercise using dumbbells.', 'categories' => ['Biceps']],
            ['name' => 'Leg Extension', 'equipment' => 'Leg Extension Machine', 'description' => 'Quadriceps isolation exercise.', 'categories' => ['Legs']],
            ['name' => 'Leg Curl', 'equipment' => 'Leg Curl Machine', 'description' => 'Hamstring isolation exercise.', 'categories' => ['Legs']],
            ['name' => 'Calf Raise', 'equipment' => 'Calf Raise Machine', 'description' => 'Calf exercise using calf raise machine.', 'categories' => ['Legs']],
            ['name' => 'Plank', 'equipment' => null, 'description' => 'Core stability exercise.', 'categories' => ['Abs']],
            ['name' => 'Crunches', 'equipment' => null, 'description' => 'Core exercise performed without equipment.', 'categories' => ['Abs']],
            ['name' => 'Shoulder Press', 'equipment' => 'Dumbbell', 'description' => 'Shoulder strength exercise using dumbbells.', 'categories' => ['Shoulders']],
        ];

        foreach ($exercises as $item) {
            $equipment = $item['equipment'] ? Equipment::where('name', $item['equipment'])->firstOrFail() : null;
            $exercise = Exercise::create([
                'name' => $item['name'],
                'equipment_id' => $equipment ? $equipment->id : null,
                'description' => $item['description'],
            ]);
            $categoryIds = Category::whereIn('name', $item['categories'])->pluck('id')->toArray();
            $exercise->categories()->sync($categoryIds);
        }
    }
}
