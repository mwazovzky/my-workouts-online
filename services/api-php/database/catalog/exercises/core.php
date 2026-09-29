<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'plank',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Duration,
        'rest_time_seconds' => 60,
        'categories' => ['core'],
        'translations' => [
            'en' => [
                'name' => 'Plank',
                'description' => 'Support yourself on your forearms and toes with your elbows under your shoulders. Hold your body in a straight line from head to heels, bracing your abs and glutes. Stop when your hips start to sag.',
            ],
            'ru' => [
                'name' => 'Планка',
                'description' => 'Примите упор на предплечьях и носках, локти под плечами. Удерживайте тело прямой линией от головы до пяток, напрягая пресс и ягодицы. Заканчивайте подход, когда таз начинает провисать.',
            ],
        ],
    ],
    [
        'slug' => 'crunch',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['core'],
        'translations' => [
            'en' => [
                'name' => 'Crunch',
                'description' => 'Lie on your back with your knees bent and your hands by your temples. Curl your shoulders off the floor by tightening your abs, pause, then lower slowly. Do not pull on your neck.',
            ],
            'ru' => [
                'name' => 'Скручивания',
                'description' => 'Лягте на спину, согнув колени, руки у висков. Оторвите лопатки от пола за счёт напряжения пресса, задержитесь и медленно опуститесь. Не тяните себя за шею.',
            ],
        ],
        'previous_names' => ['Crunches'],
    ],
];
