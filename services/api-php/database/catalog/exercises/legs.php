<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'barbell-back-squat',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 180,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Back Squat',
                'description' => 'Rest the bar on your upper back and stand with your feet shoulder-width apart. Sit back and down until your thighs are at least parallel to the floor, keeping your chest up and your knees in line with your toes, then stand back up.',
            ],
            'ru' => [
                'name' => 'Приседания со штангой',
                'description' => 'Расположите гриф на верхней части спины, ноги на ширине плеч. Опуститесь, отводя таз назад, пока бёдра не станут как минимум параллельны полу; грудь расправлена, колени направлены в сторону носков. Затем встаньте.',
            ],
        ],
        'previous_names' => ['Squat'],
    ],
    [
        'slug' => 'smith-machine-squat',
        'equipment' => 'smith-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Smith Machine Squat',
                'description' => 'Stand under the bar with it resting on your upper back, feet slightly in front of your hips. Unlock the bar, squat until your thighs are parallel to the floor, then stand up. The fixed bar path makes this a good way to learn the squat.',
            ],
            'ru' => [
                'name' => 'Приседания в машине Смита',
                'description' => 'Встаньте под гриф, расположив его на верхней части спины, стопы чуть впереди таза. Снимите гриф с упоров, присядьте до параллели бёдер с полом и встаньте. Фиксированная траектория грифа помогает освоить технику приседа.',
            ],
        ],
    ],
    [
        'slug' => 'leg-press',
        'equipment' => 'leg-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Leg Press',
                'description' => 'Sit in the machine with your feet shoulder-width apart in the middle of the platform. Release the safety, lower the platform until your knees are bent to about 90 degrees, then press it back up without locking your knees. Keep your lower back on the pad.',
            ],
            'ru' => [
                'name' => 'Жим ногами',
                'description' => 'Сядьте в тренажёр и поставьте стопы на середину платформы на ширине плеч. Снимите платформу с фиксаторов, опустите её до сгибания коленей примерно под 90 градусов и выжмите обратно, не выпрямляя колени до упора. Поясница прижата к спинке.',
            ],
        ],
    ],
    [
        'slug' => 'leg-extension',
        'equipment' => 'leg-extension-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs'],
        'translations' => [
            'en' => [
                'name' => 'Leg Extension',
                'description' => 'Sit with your knees in line with the pivot of the machine and the pad on your lower shins. Straighten your legs until they are nearly locked, pause, then lower the weight slowly.',
            ],
            'ru' => [
                'name' => 'Разгибание ног',
                'description' => 'Сядьте так, чтобы колени были на одной линии с осью тренажёра, а валик лежал на нижней части голеней. Разогните ноги почти до полного выпрямления, задержитесь и медленно опустите вес.',
            ],
        ],
    ],
    [
        'slug' => 'leg-curl',
        'equipment' => 'leg-curl-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs'],
        'translations' => [
            'en' => [
                'name' => 'Leg Curl',
                'description' => 'Set the pad just above your heels and hold the handles. Bend your knees to bring the pad toward your glutes, pause, then return slowly. Keep your hips still.',
            ],
            'ru' => [
                'name' => 'Сгибание ног',
                'description' => 'Установите валик чуть выше пяток и возьмитесь за рукояти. Согните ноги, подтягивая валик к ягодицам, задержитесь и медленно вернитесь. Таз остаётся неподвижным.',
            ],
        ],
    ],
    [
        'slug' => 'machine-calf-raise',
        'equipment' => 'calf-raise-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['calves', 'legs'],
        'translations' => [
            'en' => [
                'name' => 'Machine Calf Raise',
                'description' => 'Stand with the balls of your feet on the platform and your shoulders under the pads. Rise onto your toes as high as you can, pause, then lower your heels below the platform for a full stretch.',
            ],
            'ru' => [
                'name' => 'Подъём на носки в тренажёре',
                'description' => 'Встаньте носками на платформу, плечи под упорами. Поднимитесь на носки как можно выше, задержитесь, затем опустите пятки ниже уровня платформы до полного растяжения икр.',
            ],
        ],
        'previous_names' => ['Calf Raise'],
    ],
];
