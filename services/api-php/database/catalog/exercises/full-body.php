<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'kettlebell-swing',
        'equipment' => 'kettlebell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['full-body', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Kettlebell Swing',
                'description' => 'Stand with your feet wider than your hips and the kettlebell in both hands. Hinge at the hips to swing it back between your legs, then drive your hips forward to swing it to chest height. The power comes from your hips, not your arms.',
            ],
            'ru' => [
                'name' => 'Махи гирей',
                'description' => 'Встаньте, расставив ноги шире таза, гиря в обеих руках. Отведите таз назад, заводя гирю между ног, затем резко выпрямите таз, выбрасывая гирю до уровня груди. Движение выполняется за счёт таза, а не рук.',
            ],
        ],
    ],
    [
        'slug' => 'farmers-walk',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Duration,
        'rest_time_seconds' => 90,
        'categories' => ['full-body', 'forearms'],
        'translations' => [
            'en' => [
                'name' => "Farmer's Walk",
                'description' => 'Pick up a heavy dumbbell in each hand and stand tall. Walk with short, steady steps for the set time, keeping your shoulders back and your core braced. Put the weights down before your grip fails.',
            ],
            'ru' => [
                'name' => 'Прогулка фермера',
                'description' => 'Возьмите в каждую руку тяжёлую гантель и выпрямитесь. Идите короткими ровными шагами заданное время, плечи расправлены, корпус напряжён. Опустите гантели до того, как разожмётся хват.',
            ],
        ],
    ],
];
