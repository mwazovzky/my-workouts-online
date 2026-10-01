<?php

namespace Database\Factories;

use App\Enums\DifficultyUnit;
use App\Models\Equipment;
use Database\Factories\Concerns\HasTranslationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    use HasTranslationFactory;

    protected $model = Equipment::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Barbell', 'Machine', 'Horizontal Bar']),
            'difficulty_unit' => DifficultyUnit::Kilograms,
        ];
    }

    /**
     * Bodyweight equipment with no difficulty unit.
     */
    public function bodyweight(): static
    {
        return $this->state(fn () => [
            'difficulty_unit' => DifficultyUnit::None,
        ]);
    }

    /**
     * Machine equipment with plates difficulty unit.
     */
    public function machine(): static
    {
        return $this->state(fn () => [
            'difficulty_unit' => DifficultyUnit::Plates,
        ]);
    }

    /**
     * Equipment with pounds difficulty unit.
     */
    public function pounds(): static
    {
        return $this->state(fn () => [
            'difficulty_unit' => DifficultyUnit::Pounds,
        ]);
    }

    /**
     * Cardio equipment measured in heart-rate zones.
     */
    public function heartRateZone(): static
    {
        return $this->state(fn () => [
            'difficulty_unit' => DifficultyUnit::HeartRateZone,
        ]);
    }
}
