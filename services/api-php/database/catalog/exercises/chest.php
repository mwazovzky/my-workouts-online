<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'barbell-bench-press',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 150,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Bench Press',
                'description' => 'Lie on a flat bench with your eyes under the bar and grip it slightly wider than shoulder width. Lower the bar to mid-chest with your elbows at about 45 degrees, then press it back up until your arms are straight. Keep your feet on the floor and your shoulder blades pulled together.',
            ],
            'ru' => [
                'name' => 'Жим штанги лёжа',
                'description' => 'Лягте на горизонтальную скамью так, чтобы гриф был над глазами, и возьмитесь за него чуть шире плеч. Опустите штангу к середине груди, держа локти под углом около 45 градусов, затем выжмите её до выпрямления рук. Стопы стоят на полу, лопатки сведены.',
            ],
        ],
        'previous_names' => ['Bench Press'],
    ],
    [
        'slug' => 'machine-chest-press',
        'equipment' => 'chest-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Machine Chest Press',
                'description' => 'Set the seat so the handles are level with your mid-chest. Press the handles forward until your arms are almost straight, then return slowly without letting the weight stack touch down. Keep your back against the pad.',
            ],
            'ru' => [
                'name' => 'Жим от груди в тренажёре',
                'description' => 'Отрегулируйте сиденье так, чтобы рукояти были на уровне середины груди. Выжмите рукояти вперёд почти до выпрямления рук и медленно вернитесь, не опуская груз на стопку. Спина прижата к спинке.',
            ],
        ],
        'previous_names' => ['Chest Press'],
    ],
    [
        'slug' => 'incline-dumbbell-press',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['chest', 'shoulders'],
        'translations' => [
            'en' => [
                'name' => 'Incline Dumbbell Press',
                'description' => 'Set the bench to 30–45 degrees and hold a dumbbell in each hand at chest level. Press the dumbbells up until your arms are straight, then lower them under control to the sides of your upper chest.',
            ],
            'ru' => [
                'name' => 'Жим гантелей на наклонной скамье',
                'description' => 'Установите спинку скамьи под углом 30–45 градусов и держите гантели у груди. Выжмите гантели вверх до выпрямления рук, затем подконтрольно опустите их к верхней части груди.',
            ],
        ],
    ],
    [
        'slug' => 'pec-deck-fly',
        'equipment' => 'pec-deck-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest'],
        'translations' => [
            'en' => [
                'name' => 'Pec Deck Fly',
                'description' => 'Sit with your back against the pad and your hands on the handles, elbows slightly bent. Bring the handles together in front of your chest, squeeze for a moment, then open your arms slowly until you feel a stretch across the chest.',
            ],
            'ru' => [
                'name' => 'Сведение рук в тренажёре «Бабочка»',
                'description' => 'Сядьте, прижав спину к спинке, и возьмитесь за рукояти, слегка согнув локти. Сведите рукояти перед грудью, задержитесь на мгновение и медленно разведите руки до ощущения растяжения грудных мышц.',
            ],
        ],
        'previous_names' => ['Chest Fly'],
    ],
];
