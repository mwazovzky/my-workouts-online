<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    private array $programs = [
        [
            'en' => ['name' => 'Beginner', 'description' => 'Beginner program: repeat the same full body workout 3 days a week.'],
            'ru' => ['name' => 'Начинающий', 'description' => 'Программа для начинающих: повторяйте одну и ту же тренировку на всё тело 3 дня в неделю.'],
            'workouts' => [
                ['name' => 'Beginner Full Body Workout', 'weekday' => 'Monday'],
                ['name' => 'Beginner Full Body Workout', 'weekday' => 'Wednesday'],
                ['name' => 'Beginner Full Body Workout', 'weekday' => 'Friday'],
            ],
        ],
        [
            'en' => ['name' => '3 Day Split', 'description' => '3 Day Split: Chest/Shoulders/Triceps, Back/Biceps, Legs/Abs.'],
            'ru' => ['name' => '3-дневный сплит', 'description' => '3-дневный сплит: грудь/плечи/трицепс, спина/бицепс, ноги/пресс.'],
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
            $program = Program::firstOrCreateWithTranslations([
                'en' => $programData['en'],
                'ru' => $programData['ru'],
            ]);

            if (! $program->wasRecentlyCreated) {
                continue;
            }

            foreach ($programData['workouts'] as $workoutData) {
                $workout = WorkoutTemplate::whereTranslated('name', $workoutData['name'], 'en')->first();
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
