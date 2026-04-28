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
        [
            'en' => ['name' => 'Treadmill 3×/Week', 'description' => '30-minute treadmill run three times a week.'],
            'ru' => ['name' => 'Беговая дорожка 3×/неделю', 'description' => '30-минутный бег на беговой дорожке три раза в неделю.'],
            'workouts' => [
                ['name' => 'Treadmill Run (30 min)', 'weekday' => 'Monday'],
                ['name' => 'Treadmill Run (30 min)', 'weekday' => 'Wednesday'],
                ['name' => 'Treadmill Run (30 min)', 'weekday' => 'Friday'],
            ],
        ],
        [
            'en' => ['name' => 'Lance Armstrong Beginner Cycling', 'description' => 'Beginner cycling program based on week 5 of the Lance Armstrong plan. Six days a week with Zone 2 endurance rides, FastPedal, and Tempo intervals.'],
            'ru' => ['name' => 'Велопрограмма Лэнса Армстронга (начальный уровень)', 'description' => 'Велопрограмма для начинающих на основе 5-й недели плана Лэнса Армстронга. Шесть дней в неделю: езда в Зоне 2, FastPedal и интервалы Tempo.'],
            'workouts' => [
                ['name' => 'Cycling: Zone 2 Flat + FastPedal (30 min)', 'weekday' => 'Monday'],
                ['name' => 'Cycling: Zone 2 + Tempo (45 min)', 'weekday' => 'Tuesday'],
                ['name' => 'Cycling: Zone 2 Flat Cadence (60 min)', 'weekday' => 'Wednesday'],
                ['name' => 'Cycling: Zone 2 Flat + FastPedal (45 min)', 'weekday' => 'Thursday'],
                ['name' => 'Cycling: Zone 2 Flat + FastPedal (60 min)', 'weekday' => 'Saturday'],
                ['name' => 'Cycling: Zone 2 + Tempo (90 min)', 'weekday' => 'Sunday'],
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
