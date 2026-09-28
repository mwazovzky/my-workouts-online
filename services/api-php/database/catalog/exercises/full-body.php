<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'kettlebell-swing',
        'equipment' => 'kettlebell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs', 'back', 'shoulders'],
        'translations' => [
            'en' => ['name' => 'Kettlebell Swing', 'description' => 'Full body exercise using kettlebell.'],
            'ru' => ['name' => 'Махи гирей', 'description' => 'Упражнение на всё тело с гирей.'],
        ],
    ],
    [
        'slug' => 'farmers-walk',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Duration,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'legs'],
        'translations' => [
            'en' => ['name' => "Farmer's Walk", 'description' => 'Carry heavy weights for time.'],
            'ru' => ['name' => 'Прогулка фермера', 'description' => 'Перенос тяжёлых весов на время.'],
        ],
    ],
];
