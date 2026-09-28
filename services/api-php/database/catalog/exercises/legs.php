<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'barbell-back-squat',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs'],
        'translations' => [
            'en' => ['name' => 'Squat', 'description' => 'Leg strength exercise using a barbell.'],
            'ru' => ['name' => 'Приседания со штангой', 'description' => 'Упражнение на ноги со штангой.'],
        ],
    ],
    [
        'slug' => 'leg-press',
        'equipment' => 'leg-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs'],
        'translations' => [
            'en' => ['name' => 'Leg Press', 'description' => 'Leg exercise using leg press machine.'],
            'ru' => ['name' => 'Жим ногами', 'description' => 'Упражнение на ноги на тренажёре жим ногами.'],
        ],
    ],
    [
        'slug' => 'leg-extension',
        'equipment' => 'leg-extension-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs'],
        'translations' => [
            'en' => ['name' => 'Leg Extension', 'description' => 'Quadriceps isolation exercise.'],
            'ru' => ['name' => 'Разгибание ног', 'description' => 'Изолирующее упражнение на квадрицепсы.'],
        ],
    ],
    [
        'slug' => 'leg-curl',
        'equipment' => 'leg-curl-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs'],
        'translations' => [
            'en' => ['name' => 'Leg Curl', 'description' => 'Hamstring isolation exercise.'],
            'ru' => ['name' => 'Сгибание ног', 'description' => 'Изолирующее упражнение на бицепс бедра.'],
        ],
    ],
    [
        'slug' => 'machine-calf-raise',
        'equipment' => 'calf-raise-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs'],
        'translations' => [
            'en' => ['name' => 'Calf Raise', 'description' => 'Calf exercise using calf raise machine.'],
            'ru' => ['name' => 'Подъём на носки', 'description' => 'Упражнение на икры на тренажёре.'],
        ],
    ],
];
