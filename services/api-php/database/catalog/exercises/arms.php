<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'dumbbell-curl',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['biceps'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Curl',
                'description' => 'Stand with a dumbbell in each hand, palms facing forward. Curl the dumbbells toward your shoulders while keeping your elbows at your sides, then lower them slowly until your arms are straight.',
            ],
            'ru' => [
                'name' => 'Сгибание рук с гантелями',
                'description' => 'Встаньте прямо, гантели в опущенных руках, ладони направлены вперёд. Согните руки, поднимая гантели к плечам и удерживая локти у корпуса, затем медленно опустите до выпрямления рук.',
            ],
        ],
    ],
    [
        'slug' => 'barbell-curl',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['biceps'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Curl',
                'description' => 'Stand holding the bar with an underhand grip at shoulder width. Curl the bar toward your shoulders without moving your elbows forward, then lower it slowly. Keep your torso still.',
            ],
            'ru' => [
                'name' => 'Сгибание рук со штангой',
                'description' => 'Встаньте прямо и возьмите штангу обратным хватом на ширине плеч. Согните руки, поднимая штангу к плечам и не выводя локти вперёд, затем медленно опустите. Корпус неподвижен.',
            ],
        ],
    ],
    [
        'slug' => 'hammer-curl',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['biceps', 'forearms'],
        'translations' => [
            'en' => [
                'name' => 'Hammer Curl',
                'description' => 'Stand with a dumbbell in each hand, palms facing each other. Curl the dumbbells toward your shoulders without turning your wrists, then lower them slowly. This grip also works the forearms.',
            ],
            'ru' => [
                'name' => 'Молотковые сгибания',
                'description' => 'Встаньте прямо, гантели в опущенных руках, ладони обращены друг к другу. Согните руки, поднимая гантели к плечам и не разворачивая кисти, затем медленно опустите. Такой хват дополнительно нагружает предплечья.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-triceps-extension',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['triceps'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Overhead Triceps Extension',
                'description' => 'Hold one dumbbell with both hands and press it overhead. Lower it behind your head by bending your elbows, keeping them pointing up, then straighten your arms again.',
            ],
            'ru' => [
                'name' => 'Разгибание рук с гантелью из-за головы',
                'description' => 'Возьмите одну гантель обеими руками и поднимите её над головой. Опустите гантель за голову, сгибая руки в локтях и не разводя локти в стороны, затем выпрямите руки.',
            ],
        ],
        'previous_names' => ['Triceps Extension with Dumbbell'],
    ],
    [
        'slug' => 'cable-triceps-pushdown',
        'equipment' => 'cable-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['triceps'],
        'translations' => [
            'en' => [
                'name' => 'Cable Triceps Pushdown',
                'description' => 'Stand facing the high pulley and grip the bar or rope with your elbows at your sides. Push down until your arms are straight, then let the handle rise slowly until your forearms are just above horizontal. Only your forearms move.',
            ],
            'ru' => [
                'name' => 'Разгибание рук на блоке',
                'description' => 'Встаньте лицом к верхнему блоку и возьмитесь за рукоять или канат, прижав локти к корпусу. Разогните руки до полного выпрямления, затем медленно верните рукоять, пока предплечья не окажутся чуть выше горизонтали. Двигаются только предплечья.',
            ],
        ],
        'previous_names' => ['Triceps Extension on High Pulley Machine'],
    ],
];
