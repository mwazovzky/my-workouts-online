<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'barbell-bench-press',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => ['name' => 'Bench Press', 'description' => 'Chest strength exercise using a barbell.'],
            'ru' => ['name' => 'Жим штанги лёжа', 'description' => 'Упражнение на грудь со штангой.'],
        ],
    ],
    [
        'slug' => 'machine-chest-press',
        'equipment' => 'chest-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['chest'],
        'translations' => [
            'en' => ['name' => 'Chest Press', 'description' => 'Chest exercise using chest press machine.'],
            'ru' => ['name' => 'Жим от груди в тренажёре', 'description' => 'Упражнение на грудь на тренажёре жим от груди.'],
        ],
    ],
    [
        'slug' => 'incline-dumbbell-press',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['chest', 'shoulders'],
        'translations' => [
            'en' => ['name' => 'Incline Dumbbell Press', 'description' => 'Incline chest exercise using dumbbells.'],
            'ru' => ['name' => 'Жим гантелей на наклонной скамье', 'description' => 'Упражнение на верхнюю часть груди с гантелями.'],
        ],
    ],
    [
        'slug' => 'pec-deck-fly',
        'equipment' => 'chest-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['chest'],
        'translations' => [
            'en' => ['name' => 'Chest Fly', 'description' => 'Chest isolation exercise using chest press machine.'],
            'ru' => ['name' => 'Разведение рук на тренажёре', 'description' => 'Изолирующее упражнение на грудь на тренажёре.'],
        ],
    ],
];
