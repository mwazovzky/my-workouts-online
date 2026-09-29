<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'barbell-deadlift',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 180,
        'categories' => ['back', 'legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Deadlift',
                'description' => 'Stand with the bar over your mid-foot, feet hip-width apart, and grip it just outside your legs. With a flat back and braced core, push the floor away and stand up, keeping the bar close to your body. Lower it the same way, hinging at the hips first.',
            ],
            'ru' => [
                'name' => 'Становая тяга',
                'description' => 'Встаньте так, чтобы гриф был над серединой стопы, ноги на ширине таза, хват чуть шире ног. С прямой спиной и напряжённым корпусом оттолкнитесь от пола и выпрямитесь, ведя гриф вплотную к телу. Опускайте штангу тем же путём, начиная движение с отведения таза назад.',
            ],
        ],
        'previous_names' => ['Deadlift'],
    ],
    [
        'slug' => 'lat-pulldown',
        'equipment' => 'lat-pulldown-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Lat Pulldown',
                'description' => 'Sit with your thighs under the pads and grip the bar wider than shoulder width. Pull the bar down to your upper chest, driving your elbows toward your hips, then let it rise slowly until your arms are straight. Avoid leaning far back.',
            ],
            'ru' => [
                'name' => 'Тяга верхнего блока',
                'description' => 'Сядьте, зафиксировав бёдра под валиками, и возьмитесь за рукоять шире плеч. Потяните рукоять к верхней части груди, направляя локти вниз к корпусу, затем медленно верните её до выпрямления рук. Не отклоняйтесь сильно назад.',
            ],
        ],
    ],
    [
        'slug' => 'pull-up',
        'equipment' => 'pull-up-bar',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Pull-up',
                'description' => 'Hang from the bar with an overhand grip slightly wider than your shoulders. Pull yourself up until your chin is over the bar, then lower yourself until your arms are straight. Keep your body still and avoid swinging.',
            ],
            'ru' => [
                'name' => 'Подтягивания',
                'description' => 'Повисните на перекладине прямым хватом чуть шире плеч. Подтянитесь, пока подбородок не окажется над перекладиной, затем опуститесь до полного выпрямления рук. Не раскачивайтесь.',
            ],
        ],
    ],
    [
        'slug' => 'machine-seated-row',
        'equipment' => 'seated-row-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Machine Seated Row',
                'description' => 'Sit with your chest against the pad and take the handles with your arms straight. Pull the handles toward your lower ribs, squeezing your shoulder blades together, then return slowly. Keep your chest up and do not rock.',
            ],
            'ru' => [
                'name' => 'Тяга сидя в тренажёре',
                'description' => 'Сядьте, прижав грудь к упору, и возьмитесь за рукояти прямыми руками. Потяните рукояти к нижним рёбрам, сводя лопатки, затем медленно вернитесь. Держите грудь расправленной и не раскачивайтесь.',
            ],
        ],
        'previous_names' => ['Seated Row'],
    ],
];
