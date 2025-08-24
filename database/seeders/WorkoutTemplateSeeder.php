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
                'name' => 'Beginner Full Body Workout',
                'description' => 'A generic beginner workout covering all major muscle groups. Repeat 3 days a week.',
                'exercises' => [
                    [
                        'name' => 'Chest Press',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 20,
                    ],
                    [
                        'name' => 'Shoulder Press. Machine',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 15,
                    ],
                    [
                        'name' => 'Lat Pulldown',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 25,
                    ],
                    [
                        'name' => 'Leg Press',
                        'sets' => 3,
                        'repetitions' => 15,
                        'weight' => 50,
                    ],
                    [
                        'name' => 'Barbell Curl',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 10,
                    ],
                    [
                        'name' => 'Triceps Extension on High Pulley Machine',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 10,
                    ],
                    [
                        'name' => 'Lateral Raise',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 8,
                    ],
                    [
                        'name' => 'Crunches',
                        'sets' => 3,
                        'repetitions' => 20,
                        'weight' => null,
                    ],
                ],
            ],
            [
                'name' => 'Chest, Shoulders & Triceps',
                'description' => 'Day 1 of 3 Day Split. Focus on chest, shoulders, and triceps.',
                'exercises' => [
                    [
                        'name' => 'Bench Press',
                        'sets' => 4,
                        'repetitions' => 8,
                        'weight' => 30,
                    ],
                    [
                        'name' => 'Incline Dumbbell Press',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 15,
                    ],
                    [
                        'name' => 'Shoulder Press. Dumbbell',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 15,
                    ],
                    [
                        'name' => 'Lateral Raise',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 8,
                    ],
                    [
                        'name' => 'Triceps Extension with Dumbbell',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 10,
                    ],
                    [
                        'name' => 'Chest Fly',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 10,
                    ],
                ],
            ],
            [
                'name' => 'Back & Biceps',
                'description' => 'Day 2 of 3 Day Split. Focus on back and biceps.',
                'exercises' => [
                    [
                        'name' => 'Deadlift',
                        'sets' => 4,
                        'repetitions' => 6,
                        'weight' => 40,
                    ],
                    [
                        'name' => 'Lat Pulldown',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 25,
                    ],
                    [
                        'name' => 'Seated Row',
                        'sets' => 3,
                        'repetitions' => 10,
                        'weight' => 20,
                    ],
                    [
                        'name' => 'Pull-up',
                        'sets' => 3,
                        'repetitions' => 8,
                        'weight' => 0,
                    ],
                    [
                        'name' => 'Dumbbell Curl',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 10,
                    ],
                    [
                        'name' => 'Hammer Curl',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 10,
                    ],
                ],
            ],
            [
                'name' => 'Legs & Abs',
                'description' => 'Day 3 of 3 Day Split. Focus on legs and abs.',
                'exercises' => [
                    [
                        'name' => 'Squat',
                        'sets' => 4,
                        'repetitions' => 10,
                        'weight' => 30,
                    ],
                    [
                        'name' => 'Leg Press',
                        'sets' => 3,
                        'repetitions' => 15,
                        'weight' => 50,
                    ],
                    [
                        'name' => 'Leg Extension',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 20,
                    ],
                    [
                        'name' => 'Leg Curl',
                        'sets' => 3,
                        'repetitions' => 12,
                        'weight' => 20,
                    ],
                    [
                        'name' => 'Calf Raise',
                        'sets' => 3,
                        'repetitions' => 15,
                        'weight' => 20,
                    ],
                    [
                        'name' => 'Crunches',
                        'sets' => 3,
                        'repetitions' => 20,
                        'weight' => null,
                    ],
                    [
                        'name' => 'Plank',
                        'sets' => 3,
                        'repetitions' => 1,
                        'weight' => null,
                    ],
                ],
            ],
        ];

        foreach ($workouts as $workoutData) {
            $workout = WorkoutTemplate::create([
                'name' => $workoutData['name'],
                'description' => $workoutData['description'],
            ]);

            foreach ($workoutData['exercises'] as $order => $item) {
                $exercise = Exercise::where('name', $item['name'])->firstOrFail();

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
