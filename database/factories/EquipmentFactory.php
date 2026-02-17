<?php

namespace Database\Factories;

use App\Models\Equipment;
use Database\Factories\Concerns\HasTranslationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    use HasTranslationFactory;

    protected $model = Equipment::class;

    public function definition(): array
    {
        return [];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Equipment $equipment) {
            $equipment->translations()->createMany([
                ['locale' => 'en', 'field' => 'name', 'value' => fake()->randomElement(['Barbell', 'Machine', 'Horizontal Bar'])],
                ['locale' => 'en', 'field' => 'unit', 'value' => fake()->randomElement(['kg', 'machine unit', 'none'])],
            ]);
        });
    }
}
