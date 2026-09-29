<?php

use App\Enums\Weekday;

return [
    [
        'slug' => 'gym-beginner-full-body',
        'translations' => [
            'en' => [
                'name' => 'Gym Beginner Full Body',
                'description' => 'Three identical full-body sessions a week with a rest day between them. A good first program if you are new to the gym.',
            ],
            'ru' => [
                'name' => 'Тренажёрный зал: всё тело для начинающих',
                'description' => 'Три одинаковые тренировки на всё тело в неделю с днём отдыха между ними. Подходит как первая программа для новичков в тренажёрном зале.',
            ],
        ],
        'schedule' => [
            ['weekday' => Weekday::Monday, 'template' => 'gym-full-body-beginner'],
            ['weekday' => Weekday::Wednesday, 'template' => 'gym-full-body-beginner'],
            ['weekday' => Weekday::Friday, 'template' => 'gym-full-body-beginner'],
        ],
        'previous_names' => ['Beginner'],
    ],
    [
        'slug' => 'gym-3-day-split',
        'translations' => [
            'en' => [
                'name' => '3 Day Split',
                'description' => 'Three sessions a week, each for a different part of the body: chest, shoulders and triceps; back and biceps; legs and core.',
            ],
            'ru' => [
                'name' => '3-дневный сплит',
                'description' => 'Три тренировки в неделю, каждая на свою часть тела: грудь, плечи и трицепс; спина и бицепс; ноги и кор.',
            ],
        ],
        'schedule' => [
            ['weekday' => Weekday::Monday, 'template' => 'gym-chest-shoulders-triceps'],
            ['weekday' => Weekday::Wednesday, 'template' => 'gym-back-biceps'],
            ['weekday' => Weekday::Friday, 'template' => 'gym-legs-core'],
        ],
    ],
    [
        'slug' => 'cycling-beginner-week-1',
        'translations' => [
            'en' => ['name' => 'Cycling — Beginner (Week 1)', 'description' => 'A seven-day beginner cycling week built around heart-rate zones.'],
            'ru' => ['name' => 'Велотренировки — Начальный уровень (1 неделя)', 'description' => 'Недельная программа для начинающих велосипедистов на основе зон пульса.'],
        ],
        'schedule' => [
            ['weekday' => Weekday::Monday, 'template' => 'cycling-recovery-spin'],
            ['weekday' => Weekday::Tuesday, 'template' => 'cycling-endurance-fast-pedal-short'],
            ['weekday' => Weekday::Wednesday, 'template' => 'cycling-steady-endurance'],
            ['weekday' => Weekday::Thursday, 'template' => 'cycling-endurance-fast-pedal-short'],
            ['weekday' => Weekday::Saturday, 'template' => 'cycling-endurance-fast-pedal-long'],
            ['weekday' => Weekday::Sunday, 'template' => 'cycling-hill-endurance'],
        ],
        // Production carries the name with a plain hyphen (or en dash) instead of the em dash.
        'previous_names' => ['Cycling - Beginner (Week 1)', 'Cycling – Beginner (Week 1)'],
    ],
];
