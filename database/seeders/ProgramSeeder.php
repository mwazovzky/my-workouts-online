<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    private array $programs = [
        [
            'name' => 'Beginner',
            'description' => 'Beginner program: repeat the same full body workout 3 days a week.',
            'workouts' => [
                ['name' => 'Beginner Full Body Workout', 'weekday' => 'Monday'],
                ['name' => 'Beginner Full Body Workout', 'weekday' => 'Wednesday'],
                ['name' => 'Beginner Full Body Workout', 'weekday' => 'Friday'],
            ],
        ],
        [
            'name' => '3 Day Split',
            'description' => '3 Day Split: Chest/Shoulders/Triceps, Back/Biceps, Legs/Abs.',
            'workouts' => [
                ['name' => 'Chest, Shoulders & Triceps', 'weekday' => 'Monday'],
                ['name' => 'Back & Biceps', 'weekday' => 'Wednesday'],
                ['name' => 'Legs & Abs', 'weekday' => 'Friday'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->programs as $programData) {
            $program = Program::create([
                'name' => $programData['name'],
                'description' => $programData['description'],
            ]);

            foreach ($programData['workouts'] as $workoutData) {
                $workout = WorkoutTemplate::where('name', $workoutData['name'])->first();
                if ($workout) {
                    $program->workoutTemplates()->attach(
                        $workout->id,
                        ['weekday' => $workoutData['weekday']]
                    );
                }
            }
        }
    }
}
