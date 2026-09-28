<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'dumbbell-lateral-raise',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['shoulders'],
        'translations' => [
            'en' => ['name' => 'Lateral Raise', 'description' => 'Shoulder isolation exercise.'],
            'ru' => ['name' => 'Разведение гантелей в стороны', 'description' => 'Изолирующее упражнение на плечи.'],
        ],
    ],
    [
        'slug' => 'dumbbell-shoulder-press',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['shoulders'],
        'translations' => [
            'en' => ['name' => 'Shoulder Press. Dumbbell', 'description' => 'Shoulder strength exercise using dumbbells.'],
            'ru' => ['name' => 'Жим гантелей сидя', 'description' => 'Жим на плечи с гантелями.'],
        ],
    ],
    [
        'slug' => 'machine-shoulder-press',
        'equipment' => 'shoulder-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['shoulders'],
        'translations' => [
            'en' => ['name' => 'Shoulder Press. Machine', 'description' => 'Shoulder strength exercise using machine.'],
            'ru' => ['name' => 'Жим от плеч в тренажёре', 'description' => 'Жим на плечи в тренажёре.'],
        ],
    ],
];
