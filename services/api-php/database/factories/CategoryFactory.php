<?php

namespace Database\Factories;

use App\Models\Category;
use Database\Factories\Concerns\HasTranslationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    use HasTranslationFactory;

    protected $model = Category::class;

    public function definition(): array
    {
        return [];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category) {
            $category->translations()->createMany([
                ['locale' => 'en', 'field' => 'name', 'value' => fake()->unique()->word()],
            ]);
        });
    }
}
