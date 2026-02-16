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
        return [];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (WorkoutTemplate $template) {
            $template->translations()->createMany([
                ['locale' => 'en', 'field' => 'name', 'value' => fake()->words(2, true)],
                ['locale' => 'en', 'field' => 'description', 'value' => fake()->sentence()],
            ]);
        });
    }
}
