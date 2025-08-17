<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutLogFactory extends Factory
{
    protected $model = WorkoutLog::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'workout_template_id' => WorkoutTemplate::factory(),
            'date' => $this->faker->date(),
        ];
    }
}
