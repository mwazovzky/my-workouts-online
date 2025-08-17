<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Set;
use Illuminate\Database\Eloquent\Factories\Factory;

class SetFactory extends Factory
{
    protected $model = Set::class;

    public function definition()
    {
        return [
            'activity_id' => Activity::factory(),
            'order' => $this->faker->numberBetween(1, 5),
            'repetitions' => $this->faker->numberBetween(5, 15),
            'weight' => $this->faker->randomFloat(2, 10, 100),
        ];
    }
}
