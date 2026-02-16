<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Seeder;

class WorkoutTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $workouts = [
            [
                'en' => ['name' => 'Beginner Full Body Workout', 'description' => 'A generic beginner workout covering all major muscle groups. Repeat 3 days a week.'],
                'ru' => ['name' => 'Базовая тренировка на всё тело', 'description' => 'Универсальная тренировка для начинающих на все основные группы мышц. Повторяйте 3 раза в неделю.'],
                'exercises' => [
                    ['name' => 'Chest Press', 'sets' => 3, 'repetitions' => 10, 'weight' => 20],
                    ['name' => 'Shoulder Press. Machine', 'sets' => 3, 'repetitions' => 10, 'weight' => 15],
                    ['name' => 'Lat Pulldown', 'sets' => 3, 'repetitions' => 10, 'weight' => 25],
                    ['name' => 'Leg Press', 'sets' => 3, 'repetitions' => 15, 'weight' => 50],
                    ['name' => 'Barbell Curl', 'sets' => 3, 'repetitions' => 12, 'weight' => 10],
                    ['name' => 'Triceps Extension on High Pulley Machine', 'sets' => 3, 'repetitions' => 12, 'weight' => 10],
                    ['name' => 'Lateral Raise', 'sets' => 3, 'repetitions' => 12, 'weight' => 8],
                    ['name' => 'Crunches', 'sets' => 3, 'repetitions' => 20, 'weight' => null],
                ],
            ],
            [
                'en' => ['name' => 'Chest, Shoulders & Triceps', 'description' => 'Day 1 of 3 Day Split. Focus on chest, shoulders, and triceps.'],
                'ru' => ['name' => 'Грудь, плечи и трицепс', 'description' => 'День 1 из 3-дневного сплита. Фокус на грудь, плечи и трицепс.'],
                'exercises' => [
                    ['name' => 'Bench Press', 'sets' => 4, 'repetitions' => 8, 'weight' => 30],
                    ['name' => 'Incline Dumbbell Press', 'sets' => 3, 'repetitions' => 10, 'weight' => 15],
                    ['name' => 'Shoulder Press. Dumbbell', 'sets' => 3, 'repetitions' => 10, 'weight' => 15],
                    ['name' => 'Lateral Raise', 'sets' => 3, 'repetitions' => 12, 'weight' => 8],
                    ['name' => 'Triceps Extension with Dumbbell', 'sets' => 3, 'repetitions' => 12, 'weight' => 10],
                    ['name' => 'Chest Fly', 'sets' => 3, 'repetitions' => 12, 'weight' => 10],
                ],
            ],
            [
                'en' => ['name' => 'Back & Biceps', 'description' => 'Day 2 of 3 Day Split. Focus on back and biceps.'],
                'ru' => ['name' => 'Спина и бицепс', 'description' => 'День 2 из 3-дневного сплита. Фокус на спину и бицепс.'],
                'exercises' => [
                    ['name' => 'Deadlift', 'sets' => 4, 'repetitions' => 6, 'weight' => 40],
                    ['name' => 'Lat Pulldown', 'sets' => 3, 'repetitions' => 10, 'weight' => 25],
                    ['name' => 'Seated Row', 'sets' => 3, 'repetitions' => 10, 'weight' => 20],
                    ['name' => 'Pull-up', 'sets' => 3, 'repetitions' => 8, 'weight' => 0],
                    ['name' => 'Dumbbell Curl', 'sets' => 3, 'repetitions' => 12, 'weight' => 10],
                    ['name' => 'Hammer Curl', 'sets' => 3, 'repetitions' => 12, 'weight' => 10],
                ],
            ],
            [
                'en' => ['name' => 'Legs & Abs', 'description' => 'Day 3 of 3 Day Split. Focus on legs and abs.'],
                'ru' => ['name' => 'Ноги и пресс', 'description' => 'День 3 из 3-дневного сплита. Фокус на ноги и пресс.'],
                'exercises' => [
                    ['name' => 'Squat', 'sets' => 4, 'repetitions' => 10, 'weight' => 30],
                    ['name' => 'Leg Press', 'sets' => 3, 'repetitions' => 15, 'weight' => 50],
                    ['name' => 'Leg Extension', 'sets' => 3, 'repetitions' => 12, 'weight' => 20],
                    ['name' => 'Leg Curl', 'sets' => 3, 'repetitions' => 12, 'weight' => 20],
                    ['name' => 'Calf Raise', 'sets' => 3, 'repetitions' => 15, 'weight' => 20],
                    ['name' => 'Crunches', 'sets' => 3, 'repetitions' => 20, 'weight' => null],
                    ['name' => 'Plank', 'sets' => 3, 'repetitions' => 1, 'weight' => null],
                ],
            ],
        ];

        foreach ($workouts as $workoutData) {
            $workout = WorkoutTemplate::createWithTranslations([
                'en' => $workoutData['en'],
                'ru' => $workoutData['ru'],
            ]);

            foreach ($workoutData['exercises'] as $order => $item) {
                $exercise = Exercise::whereTranslated('name', $item['name'], 'en')->firstOrFail();

                $activity = $workout->activities()->create([
                    'exercise_id' => $exercise->id,
                    'order' => $order + 1,
                ]);

                for ($i = 1; $i <= $item['sets']; $i++) {
                    $activity->sets()->create([
                        'order' => $i,
                        'repetitions' => $item['repetitions'],
                        'weight' => $item['weight'],
                    ]);
                }
            }
        }
    }
}
