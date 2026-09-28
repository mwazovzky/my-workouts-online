<?php

/*
 * Effort is a duration in seconds; difficulty is a heart-rate zone (1-5).
 * A drill with no target zone has no difficulty.
 */
return [
    [
        'slug' => 'cycling-recovery-spin',
        'translations' => [
            'en' => ['name' => 'Recovery Spin', 'description' => '30 minutes in Zone 1 on flat terrain at an easy, steady pace.'],
            'ru' => ['name' => 'Восстановительный заезд', 'description' => '30 минут в Зоне 1 на равнинной местности в спокойном ровном темпе.'],
        ],
        'activities' => [
            ['exercise' => 'recovery-ride', 'sets' => [['effort' => 1800, 'difficulty' => 1]]],
        ],
    ],
    [
        'slug' => 'cycling-endurance-fast-pedal-short',
        'translations' => [
            'en' => ['name' => 'Endurance + FastPedal (short)', 'description' => '45 minutes in Zone 2 with a 5-minute FastPedal drill on flat terrain.'],
            'ru' => ['name' => 'Выносливость + FastPedal (коротко)', 'description' => '45 минут в Зоне 2 с 5-минутной отработкой FastPedal на равнинной местности.'],
        ],
        'activities' => [
            ['exercise' => 'endurance-ride', 'sets' => [['effort' => 2700, 'difficulty' => 2]]],
            ['exercise' => 'fast-pedal', 'sets' => [['effort' => 300]]],
        ],
    ],
    [
        'slug' => 'cycling-steady-endurance',
        'translations' => [
            'en' => ['name' => 'Steady Endurance', 'description' => '1 hour in Zone 2 on flat terrain; hold 80–85 rpm throughout.'],
            'ru' => ['name' => 'Ровная выносливость', 'description' => '1 час в Зоне 2 на равнинной местности; держите 80–85 об/мин на всей тренировке.'],
        ],
        'activities' => [
            ['exercise' => 'endurance-ride', 'sets' => [['effort' => 3600, 'difficulty' => 2]]],
        ],
    ],
    [
        'slug' => 'cycling-endurance-fast-pedal-long',
        'translations' => [
            'en' => ['name' => 'Endurance + FastPedal (long)', 'description' => '1 hour in Zone 2 with a 10-minute FastPedal drill on flat terrain.'],
            'ru' => ['name' => 'Выносливость + FastPedal (длинно)', 'description' => '1 час в Зоне 2 с 10-минутной отработкой FastPedal на равнинной местности.'],
        ],
        'activities' => [
            ['exercise' => 'endurance-ride', 'sets' => [['effort' => 3600, 'difficulty' => 2]]],
            ['exercise' => 'fast-pedal', 'sets' => [['effort' => 600]]],
        ],
    ],
    [
        'slug' => 'cycling-hill-endurance',
        'translations' => [
            'en' => ['name' => 'Hill Endurance', 'description' => '1.5 hours in Zone 2 on hilly terrain; try to stay seated on the climbs.'],
            'ru' => ['name' => 'Выносливость на холмах', 'description' => '1,5 часа в Зоне 2 на холмистой местности; старайтесь не вставать с седла на подъёмах.'],
        ],
        'activities' => [
            ['exercise' => 'endurance-ride', 'sets' => [['effort' => 5400, 'difficulty' => 2]]],
        ],
    ],
];
