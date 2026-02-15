<?php

namespace Database\Factories;

use App\Enums\WorkoutStatus;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutFactory extends Factory
{
    protected $model = Workout::class;

    public function definition()
    {
        return [
            'workout_template_id' => WorkoutTemplate::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'status' => WorkoutStatus::InProgress,
        ];
    }
}
