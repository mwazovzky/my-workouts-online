<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'equipment_id' => Equipment::factory(),
        ];
    }
}
