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
    [
        'slug' => 'barbell-row',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Bent-Over Row',
                'description' => 'Hold the bar with an overhand grip and hinge forward until your torso is close to parallel with the floor, knees slightly bent. Pull the bar to your lower ribs, then lower it until your arms are straight. Keep your back flat throughout.',
            ],
            'ru' => [
                'name' => 'Тяга штанги в наклоне',
                'description' => 'Возьмите штангу прямым хватом и наклонитесь, пока корпус не станет почти параллелен полу, колени слегка согнуты. Потяните штангу к нижним рёбрам и опустите до выпрямления рук. Спина остаётся прямой на протяжении всего движения.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-row',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'One-Arm Dumbbell Row',
                'description' => 'Place one knee and the same-side hand on a bench, with a dumbbell in the other hand. Pull the dumbbell to your hip, keeping your elbow close to your body, then lower it until your arm is straight. Finish all repetitions before changing sides.',
            ],
            'ru' => [
                'name' => 'Тяга гантели одной рукой',
                'description' => 'Обопритесь коленом и одноимённой рукой о скамью, гантель в другой руке. Потяните гантель к тазу, ведя локоть вдоль корпуса, и опустите до выпрямления руки. Выполните все повторения, затем смените сторону.',
            ],
        ],
    ],
    [
        'slug' => 'kettlebell-row',
        'equipment' => 'kettlebell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Kettlebell Row',
                'description' => 'Step one foot forward, hinge at the hips and rest your free forearm on your front thigh. Pull the kettlebell to your hip, then lower it until your arm is straight. Finish all repetitions before changing sides.',
            ],
            'ru' => [
                'name' => 'Тяга гири в наклоне',
                'description' => 'Выставьте одну ногу вперёд, наклонитесь и обопритесь свободным предплечьем о бедро. Потяните гирю к тазу и опустите до выпрямления руки. Выполните все повторения, затем смените сторону.',
            ],
        ],
    ],
    [
        'slug' => 'chin-up',
        'equipment' => 'pull-up-bar',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Chin-up',
                'description' => 'Hang from the bar with an underhand grip at shoulder width. Pull yourself up until your chin is over the bar, then lower yourself until your arms are straight. This grip uses the biceps more than a pull-up does.',
            ],
            'ru' => [
                'name' => 'Подтягивания обратным хватом',
                'description' => 'Повисните на перекладине обратным хватом на ширине плеч. Подтянитесь, пока подбородок не окажется над перекладиной, и опуститесь до выпрямления рук. Такой хват сильнее нагружает бицепс, чем прямой.',
            ],
        ],
    ],
    [
        'slug' => 'assisted-pull-up',
        'equipment' => 'assisted-pull-up-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Assisted Pull-up',
                'description' => 'Kneel or stand on the platform and grip the handles wider than your shoulders. Pull yourself up until your chin is over the handles, then lower yourself slowly. The selected weight helps you, so reduce it as you get stronger.',
            ],
            'ru' => [
                'name' => 'Подтягивания в гравитроне',
                'description' => 'Встаньте коленями или стопами на платформу и возьмитесь за рукояти шире плеч. Подтянитесь, пока подбородок не окажется над рукоятями, и медленно опуститесь. Выбранный вес помогает вам, поэтому уменьшайте его по мере роста силы.',
            ],
        ],
    ],
    [
        'slug' => 'suspension-row',
        'equipment' => 'suspension-trainer',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Suspension Row',
                'description' => 'Hold the handles and lean back with your arms straight and your body in a straight line. Pull your chest to your hands, squeezing your shoulder blades together, then lower yourself slowly. Walk your feet forward to make it harder.',
            ],
            'ru' => [
                'name' => 'Тяга на петлях',
                'description' => 'Возьмитесь за рукояти и отклонитесь назад на прямых руках, удерживая тело прямым. Подтяните грудь к кистям, сводя лопатки, и медленно опуститесь. Чтобы увеличить нагрузку, переставьте стопы вперёд.',
            ],
        ],
    ],
    [
        'slug' => 'seated-cable-row',
        'equipment' => 'cable-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Seated Cable Row',
                'description' => 'Sit at the low pulley with your feet on the platform and your knees slightly bent. Pull the handle to your stomach, squeezing your shoulder blades together, then return slowly until your arms are straight. Keep your torso upright.',
            ],
            'ru' => [
                'name' => 'Тяга нижнего блока сидя',
                'description' => 'Сядьте к нижнему блоку, стопы упираются в платформу, колени слегка согнуты. Потяните рукоять к животу, сводя лопатки, и медленно вернитесь до выпрямления рук. Корпус остаётся вертикальным.',
            ],
        ],
    ],
    [
        'slug' => 'straight-arm-pulldown',
        'equipment' => 'cable-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['back'],
        'translations' => [
            'en' => [
                'name' => 'Straight-Arm Pulldown',
                'description' => 'Stand facing the high pulley and hold the bar with straight arms at eye level. Pull the bar down in an arc to your thighs without bending your elbows, then let it rise slowly.',
            ],
            'ru' => [
                'name' => 'Тяга верхнего блока прямыми руками',
                'description' => 'Встаньте лицом к верхнему блоку и возьмитесь за рукоять прямыми руками на уровне глаз. Потяните рукоять по дуге к бёдрам, не сгибая локти, и медленно верните её.',
            ],
        ],
    ],
    [
        'slug' => 'close-grip-lat-pulldown',
        'equipment' => 'lat-pulldown-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Close-Grip Lat Pulldown',
                'description' => 'Sit with your thighs under the pads and hold a narrow handle with your palms facing each other. Pull the handle to your upper chest, keeping your elbows close to your body, then let it rise slowly until your arms are straight.',
            ],
            'ru' => [
                'name' => 'Тяга верхнего блока узким хватом',
                'description' => 'Сядьте, зафиксировав бёдра под валиками, и возьмитесь за узкую рукоять ладонями друг к другу. Потяните рукоять к верхней части груди, ведя локти вдоль корпуса, и медленно верните её до выпрямления рук.',
            ],
        ],
    ],
    [
        'slug' => 'trap-bar-deadlift',
        'equipment' => 'trap-bar',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 180,
        'categories' => ['back', 'legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Trap Bar Deadlift',
                'description' => 'Stand inside the bar with your feet hip-width apart and grip the handles. With a flat back, push the floor away and stand up, then lower the bar the same way. The handles at your sides make this easier on the lower back than a barbell deadlift.',
            ],
            'ru' => [
                'name' => 'Становая тяга с трэп-грифом',
                'description' => 'Встаньте внутрь грифа, ноги на ширине таза, и возьмитесь за рукояти. С прямой спиной оттолкнитесь от пола и выпрямитесь, затем опустите гриф тем же путём. Рукояти по бокам снижают нагрузку на поясницу по сравнению со становой тягой со штангой.',
            ],
        ],
    ],
    [
        'slug' => 'back-extension',
        'equipment' => 'back-extension-bench',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['back', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Back Extension',
                'description' => 'Set the pad just below your hips and cross your arms over your chest. Bend forward at the hips with a flat back, then raise your torso until your body is in a straight line. Do not arch past that line.',
            ],
            'ru' => [
                'name' => 'Гиперэкстензия',
                'description' => 'Установите валики чуть ниже таза и скрестите руки на груди. Наклонитесь вперёд, сгибаясь в тазобедренных суставах и сохраняя спину прямой, затем поднимите корпус до прямой линии тела. Не прогибайтесь выше этой линии.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-shrug',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['back', 'shoulders'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Shrug',
                'description' => 'Stand with a dumbbell in each hand at your sides. Raise your shoulders straight up toward your ears, pause, then lower them slowly. Keep your arms straight and do not roll your shoulders.',
            ],
            'ru' => [
                'name' => 'Шраги с гантелями',
                'description' => 'Встаньте прямо, гантели в опущенных руках. Поднимите плечи вертикально вверх к ушам, задержитесь и медленно опустите. Руки остаются прямыми, вращать плечами не нужно.',
            ],
        ],
    ],
    [
        'slug' => 'band-row',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['back', 'biceps'],
        'translations' => [
            'en' => [
                'name' => 'Band Seated Row',
                'description' => 'Sit on the floor with your legs straight and the band looped around your feet. Pull the ends to your stomach, squeezing your shoulder blades together, then return slowly. Keep your torso upright.',
            ],
            'ru' => [
                'name' => 'Тяга эспандера сидя',
                'description' => 'Сядьте на пол, выпрямив ноги, и зацепите эспандер за стопы. Потяните концы эспандера к животу, сводя лопатки, и медленно вернитесь. Корпус остаётся вертикальным.',
            ],
        ],
    ],
    [
        'slug' => 'band-lat-pulldown',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['back'],
        'translations' => [
            'en' => [
                'name' => 'Band Lat Pulldown',
                'description' => 'Anchor the band above your head, for example over a door, and kneel facing it. Pull the ends down to your shoulders, driving your elbows toward your hips, then let your arms rise slowly.',
            ],
            'ru' => [
                'name' => 'Тяга эспандера сверху',
                'description' => 'Закрепите эспандер над головой, например на двери, и встаньте на колени лицом к нему. Потяните концы эспандера к плечам, направляя локти вниз к корпусу, и медленно выпрямите руки.',
            ],
        ],
    ],
    [
        'slug' => 'band-pull-apart',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['back', 'shoulders'],
        'translations' => [
            'en' => [
                'name' => 'Band Pull-Apart',
                'description' => 'Hold the band in front of your chest with straight arms, hands shoulder-width apart. Pull your hands apart until the band touches your chest, squeezing your shoulder blades together, then return slowly.',
            ],
            'ru' => [
                'name' => 'Растягивание эспандера перед грудью',
                'description' => 'Держите эспандер перед грудью на прямых руках, кисти на ширине плеч. Разведите руки в стороны, пока эспандер не коснётся груди, сводя лопатки, и медленно вернитесь.',
            ],
        ],
    ],
    [
        'slug' => 'superman',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['back', 'core'],
        'translations' => [
            'en' => [
                'name' => 'Superman',
                'description' => 'Lie face down with your arms stretched out in front of you. Raise your arms, chest and legs off the floor at the same time, pause for a second, then lower them. Look at the floor to keep your neck relaxed.',
            ],
            'ru' => [
                'name' => 'Супермен',
                'description' => 'Лягте на живот, вытянув руки перед собой. Одновременно оторвите от пола руки, грудь и ноги, задержитесь на секунду и опуститесь. Смотрите в пол, чтобы не напрягать шею.',
            ],
        ],
    ],
];
