<?php

namespace Database\Seeders;

use App\Enums\DifficultyUnit;
use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = [
            ['en' => ['name' => 'Barbell'], 'ru' => ['name' => 'Штанга'], 'difficulty_unit' => DifficultyUnit::Kilograms],
            ['en' => ['name' => 'Dumbbell'], 'ru' => ['name' => 'Гантель'], 'difficulty_unit' => DifficultyUnit::Kilograms],
            ['en' => ['name' => 'Kettlebell'], 'ru' => ['name' => 'Гиря'], 'difficulty_unit' => DifficultyUnit::Kilograms],
            ['en' => ['name' => 'Smith Machine'], 'ru' => ['name' => 'Машина Смита'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Cable Machine'], 'ru' => ['name' => 'Тросовый тренажёр'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Leg Press Machine'], 'ru' => ['name' => 'Тренажёр для жима ногами'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Chest Press Machine'], 'ru' => ['name' => 'Тренажёр для жима от груди'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Shoulder Press Machine'], 'ru' => ['name' => 'Тренажёр для жима от плеч'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Seated Row Machine'], 'ru' => ['name' => 'Тренажёр для тяги сидя'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'High Pulley Machine'], 'ru' => ['name' => 'Тренажёр верхнего блока'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Lat Pulldown Machine'], 'ru' => ['name' => 'Тренажёр тяги верхнего блока'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Leg Extension Machine'], 'ru' => ['name' => 'Тренажёр для разгибания ног'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Leg Curl Machine'], 'ru' => ['name' => 'Тренажёр для сгибания ног'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Calf Raise Machine'], 'ru' => ['name' => 'Тренажёр для подъёма на носки'], 'difficulty_unit' => DifficultyUnit::Plates],
            ['en' => ['name' => 'Bench'], 'ru' => ['name' => 'Скамья'], 'difficulty_unit' => DifficultyUnit::None],
            ['en' => ['name' => 'Pull-up Bar'], 'ru' => ['name' => 'Турник'], 'difficulty_unit' => DifficultyUnit::None],
            ['en' => ['name' => 'Treadmill'], 'ru' => ['name' => 'Беговая дорожка'], 'difficulty_unit' => DifficultyUnit::None],
            ['en' => ['name' => 'Stationary Bike'], 'ru' => ['name' => 'Велотренажёр'], 'difficulty_unit' => DifficultyUnit::None],
            ['en' => ['name' => 'Bodyweight'], 'ru' => ['name' => 'Собственный вес'], 'difficulty_unit' => DifficultyUnit::None],
        ];

        foreach ($equipment as $item) {
            Equipment::firstOrCreateWithTranslations(
                ['en' => $item['en'], 'ru' => $item['ru']],
                ['difficulty_unit' => $item['difficulty_unit']],
            );
        }
    }
}
