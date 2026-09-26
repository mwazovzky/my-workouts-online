<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Set;
use Illuminate\Database\Eloquent\Factories\Factory;

class SetFactory extends Factory
{
    protected $model = Set::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'order' => $this->faker->numberBetween(1, 5),
            'effort_value' => $this->faker->numberBetween(5, 15),
            'difficulty_value' => $this->faker->numberBetween(10, 100),
            'is_completed' => $this->faker->boolean(20),
        ];
    }

    /**
     * Bodyweight set with no difficulty value.
     */
    public function bodyweight(): static
    {
        return $this->state(fn () => [
            'difficulty_value' => null,
        ]);
    }

    /**
     * Timed/duration set (effort_value represents seconds).
     */
    public function timed(): static
    {
        return $this->state(fn () => [
            'effort_value' => $this->faker->numberBetween(30, 120),
        ]);
    }
}
