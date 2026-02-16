<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\Exercise;
use Database\Factories\Concerns\HasTranslationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseFactory extends Factory
{
    use HasTranslationFactory;

    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'rest_time_seconds' => $this->faker->randomElement([30, 45, 60, 90, 120, 180]),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Exercise $exercise) {
            $exercise->translations()->createMany([
                ['locale' => 'en', 'field' => 'name', 'value' => fake()->unique()->word()],
                ['locale' => 'en', 'field' => 'description', 'value' => fake()->sentence()],
            ]);
        });
    }
}
