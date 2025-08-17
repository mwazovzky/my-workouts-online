<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition()
    {
        return [
            'name' => $this->faker->randomElement(['Barbell', 'Machine', 'Horizontal Bar']),
            'unit' => $this->faker->randomElement(['kg', 'machine unit', 'none']),
        ];
    }
}
