<?php

namespace Database\Factories;

use App\Models\WorkoutTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutTemplateFactory extends Factory
{
    protected $model = WorkoutTemplate::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence,
        ];
    }
}
