<?php

/*
 * Templates prescribe sets and repetitions but no weight: the right weight
 * differs for every person, so each user enters their own.
 */
return [
    [
        'slug' => 'gym-full-body-beginner',
        'translations' => [
            'en' => [
                'name' => 'Beginner Full Body',
                'description' => 'Eight exercises that cover every major muscle group, mostly on machines. Choose a weight that lets you finish every set with two repetitions to spare.',
            ],
            'ru' => [
                'name' => 'Всё тело для начинающих',
                'description' => 'Восемь упражнений на все основные группы мышц, в основном в тренажёрах. Подберите вес, с которым в каждом подходе остаётся запас в два повторения.',
            ],
        ],
        'activities' => [
            ['exercise' => 'machine-chest-press', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'machine-shoulder-press', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'lat-pulldown', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'leg-press', 'sets' => 3, 'effort' => 15],
            ['exercise' => 'barbell-curl', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'cable-triceps-pushdown', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'dumbbell-lateral-raise', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'crunch', 'sets' => 3, 'effort' => 20],
        ],
        'previous_names' => ['Beginner Full Body Workout'],
    ],
    [
        'slug' => 'gym-chest-shoulders-triceps',
        'translations' => [
            'en' => [
                'name' => 'Chest, Shoulders & Triceps',
                'description' => 'Pressing day: chest first, then shoulders and triceps. Start with the bench press while you are fresh.',
            ],
            'ru' => [
                'name' => 'Грудь, плечи и трицепс',
                'description' => 'День жимов: сначала грудь, затем плечи и трицепс. Начинайте с жима лёжа, пока есть силы.',
            ],
        ],
        'activities' => [
            ['exercise' => 'barbell-bench-press', 'sets' => 4, 'effort' => 8],
            ['exercise' => 'incline-dumbbell-press', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'dumbbell-shoulder-press', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'dumbbell-lateral-raise', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'dumbbell-triceps-extension', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'pec-deck-fly', 'sets' => 3, 'effort' => 12],
        ],
    ],
    [
        'slug' => 'gym-back-biceps',
        'translations' => [
            'en' => [
                'name' => 'Back & Biceps',
                'description' => 'Pulling day: the deadlift first, then pulldowns, rows and pull-ups for the back, and curls to finish.',
            ],
            'ru' => [
                'name' => 'Спина и бицепс',
                'description' => 'День тяг: сначала становая тяга, затем тяги и подтягивания для спины и сгибания рук в завершение.',
            ],
        ],
        'activities' => [
            ['exercise' => 'barbell-deadlift', 'sets' => 4, 'effort' => 6],
            ['exercise' => 'lat-pulldown', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'machine-seated-row', 'sets' => 3, 'effort' => 10],
            ['exercise' => 'pull-up', 'sets' => 3, 'effort' => 8],
            ['exercise' => 'dumbbell-curl', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'hammer-curl', 'sets' => 3, 'effort' => 12],
        ],
    ],
    [
        'slug' => 'gym-legs-core',
        'translations' => [
            'en' => [
                'name' => 'Legs & Core',
                'description' => 'Squat and leg press first, then isolation work for the thighs and calves, and two core exercises to finish.',
            ],
            'ru' => [
                'name' => 'Ноги и кор',
                'description' => 'Сначала приседания и жим ногами, затем изолирующие упражнения для бёдер и икр и два упражнения на кор в завершение.',
            ],
        ],
        'activities' => [
            ['exercise' => 'barbell-back-squat', 'sets' => 4, 'effort' => 10],
            ['exercise' => 'leg-press', 'sets' => 3, 'effort' => 15],
            ['exercise' => 'leg-extension', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'leg-curl', 'sets' => 3, 'effort' => 12],
            ['exercise' => 'machine-calf-raise', 'sets' => 3, 'effort' => 15],
            ['exercise' => 'crunch', 'sets' => 3, 'effort' => 20],
            ['exercise' => 'plank', 'sets' => 3, 'effort' => 60],
        ],
        'previous_names' => ['Legs & Abs'],
    ],
];
