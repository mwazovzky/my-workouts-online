<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'barbell-deadlift',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'legs'],
        'translations' => [
            'en' => ['name' => 'Deadlift', 'description' => 'Full body strength exercise using a barbell.'],
            'ru' => ['name' => 'Становая тяга', 'description' => 'Базовое упражнение на всё тело со штангой.'],
        ],
    ],
    [
        'slug' => 'lat-pulldown',
        'equipment' => 'lat-pulldown-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back'],
        'translations' => [
            'en' => ['name' => 'Lat Pulldown', 'description' => 'Back exercise using lat pulldown machine.'],
            'ru' => ['name' => 'Тяга верхнего блока', 'description' => 'Упражнение на спину на тренажёре верхнего блока.'],
        ],
    ],
    [
        'slug' => 'pull-up',
        'equipment' => 'pull-up-bar',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => ['name' => 'Pull-up', 'description' => 'Back and arms exercise using pull-up bar.'],
            'ru' => ['name' => 'Подтягивания', 'description' => 'Упражнение на спину и руки на турнике.'],
        ],
    ],
    [
        'slug' => 'machine-seated-row',
        'equipment' => 'seated-row-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back'],
        'translations' => [
            'en' => ['name' => 'Seated Row', 'description' => 'Back exercise using seated row machine.'],
            'ru' => ['name' => 'Тяга сидя', 'description' => 'Упражнение на спину на тренажёре тяги сидя.'],
        ],
    ],
];
