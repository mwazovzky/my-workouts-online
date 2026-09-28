<?php

return [
    [
        'slug' => 'gym-full-body-beginner',
        'translations' => [
            'en' => ['name' => 'Beginner Full Body Workout', 'description' => 'A generic beginner workout covering all major muscle groups. Repeat 3 days a week.'],
            'ru' => ['name' => 'Базовая тренировка на всё тело', 'description' => 'Универсальная тренировка для начинающих на все основные группы мышц. Повторяйте 3 раза в неделю.'],
        ],
        'activities' => [
            ['exercise' => 'machine-chest-press', 'sets' => 3, 'effort' => 10, 'difficulty' => 20],
            ['exercise' => 'machine-shoulder-press', 'sets' => 3, 'effort' => 10, 'difficulty' => 15],
            ['exercise' => 'lat-pulldown', 'sets' => 3, 'effort' => 10, 'difficulty' => 25],
            ['exercise' => 'leg-press', 'sets' => 3, 'effort' => 15, 'difficulty' => 50],
            ['exercise' => 'barbell-curl', 'sets' => 3, 'effort' => 12, 'difficulty' => 10],
            ['exercise' => 'cable-triceps-pushdown', 'sets' => 3, 'effort' => 12, 'difficulty' => 10],
            ['exercise' => 'dumbbell-lateral-raise', 'sets' => 3, 'effort' => 12, 'difficulty' => 8],
            ['exercise' => 'crunch', 'sets' => 3, 'effort' => 20],
        ],
    ],
    [
        'slug' => 'gym-chest-shoulders-triceps',
        'translations' => [
            'en' => ['name' => 'Chest, Shoulders & Triceps', 'description' => 'Day 1 of 3 Day Split. Focus on chest, shoulders, and triceps.'],
            'ru' => ['name' => 'Грудь, плечи и трицепс', 'description' => 'День 1 из 3-дневного сплита. Фокус на грудь, плечи и трицепс.'],
        ],
        'activities' => [
            ['exercise' => 'barbell-bench-press', 'sets' => 4, 'effort' => 8, 'difficulty' => 30],
            ['exercise' => 'incline-dumbbell-press', 'sets' => 3, 'effort' => 10, 'difficulty' => 15],
            ['exercise' => 'dumbbell-shoulder-press', 'sets' => 3, 'effort' => 10, 'difficulty' => 15],
            ['exercise' => 'dumbbell-lateral-raise', 'sets' => 3, 'effort' => 12, 'difficulty' => 8],
            ['exercise' => 'dumbbell-triceps-extension', 'sets' => 3, 'effort' => 12, 'difficulty' => 10],
            ['exercise' => 'pec-deck-fly', 'sets' => 3, 'effort' => 12, 'difficulty' => 10],
        ],
    ],
    [
        'slug' => 'gym-back-biceps',
        'translations' => [
            'en' => ['name' => 'Back & Biceps', 'description' => 'Day 2 of 3 Day Split. Focus on back and biceps.'],
            'ru' => ['name' => 'Спина и бицепс', 'description' => 'День 2 из 3-дневного сплита. Фокус на спину и бицепс.'],
        ],
        'activities' => [
            ['exercise' => 'barbell-deadlift', 'sets' => 4, 'effort' => 6, 'difficulty' => 40],
            ['exercise' => 'lat-pulldown', 'sets' => 3, 'effort' => 10, 'difficulty' => 25],
            ['exercise' => 'machine-seated-row', 'sets' => 3, 'effort' => 10, 'difficulty' => 20],
            ['exercise' => 'pull-up', 'sets' => 3, 'effort' => 8],
            ['exercise' => 'dumbbell-curl', 'sets' => 3, 'effort' => 12, 'difficulty' => 10],
            ['exercise' => 'hammer-curl', 'sets' => 3, 'effort' => 12, 'difficulty' => 10],
        ],
    ],
    [
        'slug' => 'gym-legs-core',
        'translations' => [
            'en' => ['name' => 'Legs & Abs', 'description' => 'Day 3 of 3 Day Split. Focus on legs and abs.'],
            'ru' => ['name' => 'Ноги и пресс', 'description' => 'День 3 из 3-дневного сплита. Фокус на ноги и пресс.'],
        ],
        'activities' => [
            ['exercise' => 'barbell-back-squat', 'sets' => 4, 'effort' => 10, 'difficulty' => 30],
            ['exercise' => 'leg-press', 'sets' => 3, 'effort' => 15, 'difficulty' => 50],
            ['exercise' => 'leg-extension', 'sets' => 3, 'effort' => 12, 'difficulty' => 20],
            ['exercise' => 'leg-curl', 'sets' => 3, 'effort' => 12, 'difficulty' => 20],
            ['exercise' => 'machine-calf-raise', 'sets' => 3, 'effort' => 15, 'difficulty' => 20],
            ['exercise' => 'crunch', 'sets' => 3, 'effort' => 20],
            ['exercise' => 'plank', 'sets' => 3, 'effort' => 60],
        ],
    ],
];
