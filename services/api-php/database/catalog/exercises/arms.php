<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'dumbbell-curl',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['biceps'],
        'translations' => [
            'en' => ['name' => 'Dumbbell Curl', 'description' => 'Biceps exercise using dumbbells.'],
            'ru' => ['name' => 'Сгибание рук с гантелями', 'description' => 'Упражнение на бицепс с гантелями.'],
        ],
    ],
    [
        'slug' => 'barbell-curl',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['biceps'],
        'translations' => [
            'en' => ['name' => 'Barbell Curl', 'description' => 'Biceps exercise using a barbell.'],
            'ru' => ['name' => 'Сгибание рук со штангой', 'description' => 'Упражнение на бицепс со штангой.'],
        ],
    ],
    [
        'slug' => 'hammer-curl',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['biceps'],
        'translations' => [
            'en' => ['name' => 'Hammer Curl', 'description' => 'Biceps exercise using dumbbells.'],
            'ru' => ['name' => 'Молотковые сгибания', 'description' => 'Упражнение на бицепс с гантелями.'],
        ],
    ],
    [
        'slug' => 'dumbbell-triceps-extension',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['triceps'],
        'translations' => [
            'en' => ['name' => 'Triceps Extension with Dumbbell', 'description' => 'Triceps exercise using dumbbells.'],
            'ru' => ['name' => 'Разгибание рук с гантелью', 'description' => 'Упражнение на трицепс с гантелями.'],
        ],
    ],
    [
        'slug' => 'cable-triceps-pushdown',
        'equipment' => 'high-pulley-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['triceps'],
        'translations' => [
            'en' => ['name' => 'Triceps Extension on High Pulley Machine', 'description' => 'Triceps exercise using high pulley machine.'],
            'ru' => ['name' => 'Разгибание рук на верхнем блоке', 'description' => 'Упражнение на трицепс на верхнем блоке.'],
        ],
    ],
];
