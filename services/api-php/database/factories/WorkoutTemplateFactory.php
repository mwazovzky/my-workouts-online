<?php

namespace Database\Factories;

use App\Models\WorkoutTemplate;
use Database\Factories\Concerns\HasTranslationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutTemplateFactory extends Factory
{
    use HasTranslationFactory;

    protected $model = WorkoutTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
        ];
    }
}
