<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $equipment = [
            ['en' => ['name' => 'Barbell', 'unit' => 'kg'], 'ru' => ['name' => 'Штанга', 'unit' => 'кг']],
            ['en' => ['name' => 'Dumbbell', 'unit' => 'kg'], 'ru' => ['name' => 'Гантель', 'unit' => 'кг']],
            ['en' => ['name' => 'Kettlebell', 'unit' => 'kg'], 'ru' => ['name' => 'Гиря', 'unit' => 'кг']],
            ['en' => ['name' => 'Smith Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Машина Смита', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Cable Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тросовый тренажёр', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Leg Press Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для жима ногами', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Chest Press Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для жима от груди', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Shoulder Press Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для жима от плеч', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Seated Row Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для тяги сидя', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'High Pulley Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр верхнего блока', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Lat Pulldown Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр тяги верхнего блока', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Leg Extension Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для разгибания ног', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Leg Curl Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для сгибания ног', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Calf Raise Machine', 'unit' => 'machine unit'], 'ru' => ['name' => 'Тренажёр для подъёма на носки', 'unit' => 'ед. тренажёра']],
            ['en' => ['name' => 'Bench', 'unit' => 'none'], 'ru' => ['name' => 'Скамья', 'unit' => 'нет']],
            ['en' => ['name' => 'Pull-up Bar', 'unit' => 'none'], 'ru' => ['name' => 'Турник', 'unit' => 'нет']],
            ['en' => ['name' => 'Treadmill', 'unit' => 'none'], 'ru' => ['name' => 'Беговая дорожка', 'unit' => 'нет']],
        ];

        foreach ($equipment as $item) {
            Equipment::createWithTranslations([
                'en' => $item['en'],
                'ru' => $item['ru'],
            ]);
        }
    }
}
