<?php

use App\Enums\DifficultyUnit;

return [
    [
        'slug' => 'barbell',
        'difficulty_unit' => DifficultyUnit::Kilograms,
        'translations' => ['en' => ['name' => 'Barbell'], 'ru' => ['name' => 'Штанга']],
    ],
    [
        'slug' => 'bodyweight',
        'difficulty_unit' => DifficultyUnit::None,
        'translations' => ['en' => ['name' => 'Bodyweight'], 'ru' => ['name' => 'Собственный вес']],
    ],
    [
        'slug' => 'indoor-bike',
        'difficulty_unit' => DifficultyUnit::HeartRateZone,
        'translations' => ['en' => ['name' => 'Indoor Bike'], 'ru' => ['name' => 'Велотренажёр']],
    ],
];
