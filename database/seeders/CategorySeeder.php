<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['en' => 'Chest', 'ru' => 'Грудь'],
            ['en' => 'Shoulders', 'ru' => 'Плечи'],
            ['en' => 'Triceps', 'ru' => 'Трицепс'],
            ['en' => 'Back', 'ru' => 'Спина'],
            ['en' => 'Biceps', 'ru' => 'Бицепс'],
            ['en' => 'Legs', 'ru' => 'Ноги'],
            ['en' => 'Abs', 'ru' => 'Пресс'],
            ['en' => 'Cardio', 'ru' => 'Кардио'],
        ];

        foreach ($categories as $names) {
            Category::firstOrCreateWithTranslations([
                'en' => ['name' => $names['en']],
                'ru' => ['name' => $names['ru']],
            ]);
        }
    }
}
