<?php

use App\Enums\DifficultyUnit;

return [
    [
        'slug' => 'barbell',
        'difficulty_unit' => DifficultyUnit::Kilograms,
        'translations' => ['en' => ['name' => 'Barbell'], 'ru' => ['name' => 'Штанга']],
    ],
    [
        'slug' => 'dumbbell',
        'difficulty_unit' => DifficultyUnit::Kilograms,
        'translations' => ['en' => ['name' => 'Dumbbell'], 'ru' => ['name' => 'Гантель']],
    ],
    [
        'slug' => 'kettlebell',
        'difficulty_unit' => DifficultyUnit::Kilograms,
        'translations' => ['en' => ['name' => 'Kettlebell'], 'ru' => ['name' => 'Гиря']],
    ],
    [
        'slug' => 'smith-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Smith Machine'], 'ru' => ['name' => 'Машина Смита']],
    ],
    [
        'slug' => 'cable-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Cable Machine'], 'ru' => ['name' => 'Тросовый тренажёр']],
    ],
    [
        'slug' => 'pec-deck-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Pec Deck Machine'], 'ru' => ['name' => 'Тренажёр «Бабочка»']],
    ],
    [
        'slug' => 'leg-press-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Leg Press Machine'], 'ru' => ['name' => 'Тренажёр для жима ногами']],
    ],
    [
        'slug' => 'chest-press-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Chest Press Machine'], 'ru' => ['name' => 'Тренажёр для жима от груди']],
    ],
    [
        'slug' => 'shoulder-press-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Shoulder Press Machine'], 'ru' => ['name' => 'Тренажёр для жима от плеч']],
    ],
    [
        'slug' => 'seated-row-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Seated Row Machine'], 'ru' => ['name' => 'Тренажёр для тяги сидя']],
    ],
    [
        'slug' => 'lat-pulldown-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Lat Pulldown Machine'], 'ru' => ['name' => 'Тренажёр тяги верхнего блока']],
    ],
    [
        'slug' => 'leg-extension-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Leg Extension Machine'], 'ru' => ['name' => 'Тренажёр для разгибания ног']],
    ],
    [
        'slug' => 'leg-curl-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Leg Curl Machine'], 'ru' => ['name' => 'Тренажёр для сгибания ног']],
    ],
    [
        'slug' => 'calf-raise-machine',
        'difficulty_unit' => DifficultyUnit::Plates,
        'translations' => ['en' => ['name' => 'Calf Raise Machine'], 'ru' => ['name' => 'Тренажёр для подъёма на носки']],
    ],
    [
        'slug' => 'pull-up-bar',
        'difficulty_unit' => DifficultyUnit::None,
        'translations' => ['en' => ['name' => 'Pull-up Bar'], 'ru' => ['name' => 'Турник']],
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
