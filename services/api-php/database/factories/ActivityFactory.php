<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_type' => WorkoutTemplate::class,
            'workout_id' => WorkoutTemplate::factory(),
            'exercise_id' => Exercise::factory(),
            'order' => $this->faker->numberBetween(1, 5),
        ];
    }
}
