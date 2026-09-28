<?php

use App\Enums\Weekday;

return [
    [
        'slug' => 'gym-beginner-full-body',
        'translations' => [
            'en' => ['name' => 'Beginner', 'description' => 'Beginner program: repeat the same full body workout 3 days a week.'],
            'ru' => ['name' => 'Начинающий', 'description' => 'Программа для начинающих: повторяйте одну и ту же тренировку на всё тело 3 дня в неделю.'],
        ],
        'schedule' => [
            ['weekday' => Weekday::Monday, 'template' => 'gym-full-body-beginner'],
            ['weekday' => Weekday::Wednesday, 'template' => 'gym-full-body-beginner'],
            ['weekday' => Weekday::Friday, 'template' => 'gym-full-body-beginner'],
        ],
    ],
    [
        'slug' => 'gym-3-day-split',
        'translations' => [
            'en' => ['name' => '3 Day Split', 'description' => '3 Day Split: Chest/Shoulders/Triceps, Back/Biceps, Legs/Abs.'],
            'ru' => ['name' => '3-дневный сплит', 'description' => '3-дневный сплит: грудь/плечи/трицепс, спина/бицепс, ноги/пресс.'],
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
    ],
];
