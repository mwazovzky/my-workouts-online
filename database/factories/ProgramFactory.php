<?php

namespace Database\Factories;

use App\Models\Program;
use Database\Factories\Concerns\HasTranslationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    use HasTranslationFactory;

    protected $model = Program::class;

    public function definition(): array
    {
        return [];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Program $program) {
            $program->translations()->createMany([
                ['locale' => 'en', 'field' => 'name', 'value' => fake()->words(2, true)],
                ['locale' => 'en', 'field' => 'description', 'value' => fake()->sentence()],
            ]);
        });
    }
}
