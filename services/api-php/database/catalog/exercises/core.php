<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'plank',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Duration,
        'rest_time_seconds' => 90,
        'categories' => ['core'],
        'translations' => [
            'en' => ['name' => 'Plank', 'description' => 'Core stability exercise.'],
            'ru' => ['name' => 'Планка', 'description' => 'Упражнение на стабилизацию корпуса.'],
        ],
    ],
    [
        'slug' => 'crunch',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['core'],
        'translations' => [
            'en' => ['name' => 'Crunches', 'description' => 'Core exercise performed without equipment.'],
            'ru' => ['name' => 'Скручивания', 'description' => 'Упражнение на пресс без оборудования.'],
        ],
    ],
];
