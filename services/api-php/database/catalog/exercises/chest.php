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
    [
        'slug' => 'dumbbell-bench-press',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Bench Press',
                'description' => 'Lie on a flat bench with a dumbbell in each hand at the sides of your chest. Press the dumbbells up until your arms are straight, then lower them slowly until your elbows are just below the bench. Keep your wrists above your elbows.',
            ],
            'ru' => [
                'name' => 'Жим гантелей лёжа',
                'description' => 'Лягте на горизонтальную скамью, гантели у груди по бокам. Выжмите гантели вверх до выпрямления рук, затем медленно опустите, пока локти не окажутся чуть ниже уровня скамьи. Кисти находятся над локтями.',
            ],
        ],
    ],
    [
        'slug' => 'incline-barbell-bench-press',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 150,
        'categories' => ['chest', 'shoulders'],
        'translations' => [
            'en' => [
                'name' => 'Incline Barbell Bench Press',
                'description' => 'Set the bench to 30–45 degrees and grip the bar slightly wider than shoulder width. Lower the bar to your upper chest, then press it up until your arms are straight. Keep your shoulder blades pulled together.',
            ],
            'ru' => [
                'name' => 'Жим штанги на наклонной скамье',
                'description' => 'Установите спинку скамьи под углом 30–45 градусов и возьмитесь за гриф чуть шире плеч. Опустите штангу к верхней части груди и выжмите её до выпрямления рук. Лопатки сведены.',
            ],
        ],
    ],
    [
        'slug' => 'smith-machine-bench-press',
        'equipment' => 'smith-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Smith Machine Bench Press',
                'description' => 'Place a flat bench under the bar so that it lowers to your mid-chest. Unlock the bar, lower it to your chest, then press it up until your arms are straight. Lock the bar again before you sit up.',
            ],
            'ru' => [
                'name' => 'Жим лёжа в машине Смита',
                'description' => 'Поставьте горизонтальную скамью под гриф так, чтобы он опускался к середине груди. Снимите гриф с упоров, опустите его к груди и выжмите до выпрямления рук. Верните гриф на упоры, прежде чем вставать.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-fly',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Fly',
                'description' => 'Lie on a flat bench with the dumbbells above your chest, palms facing each other and elbows slightly bent. Open your arms in a wide arc until you feel a stretch across your chest, then bring the dumbbells back together. Use a light weight.',
            ],
            'ru' => [
                'name' => 'Разведение гантелей лёжа',
                'description' => 'Лягте на горизонтальную скамью, гантели над грудью, ладони обращены друг к другу, локти слегка согнуты. Разведите руки по широкой дуге до ощущения растяжения грудных мышц и сведите гантели обратно. Используйте небольшой вес.',
            ],
        ],
    ],
    [
        'slug' => 'cable-crossover',
        'equipment' => 'cable-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest'],
        'translations' => [
            'en' => [
                'name' => 'Cable Crossover',
                'description' => 'Stand between two high pulleys with a handle in each hand and one foot forward. With your elbows slightly bent, bring your hands together in front of your hips, then let them return slowly until your chest is stretched.',
            ],
            'ru' => [
                'name' => 'Сведение рук в кроссовере',
                'description' => 'Встаньте между двумя верхними блоками, взяв по рукояти в каждую руку и выставив одну ногу вперёд. Слегка согнув локти, сведите руки перед собой на уровне таза и медленно верните их до растяжения грудных мышц.',
            ],
        ],
    ],
    [
        'slug' => 'push-up',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Push-up',
                'description' => 'Place your hands slightly wider than your shoulders and hold your body in a straight line from head to heels. Lower your chest to just above the floor, then push back up. Keep your elbows at about 45 degrees to your body.',
            ],
            'ru' => [
                'name' => 'Отжимания',
                'description' => 'Поставьте ладони чуть шире плеч и удерживайте тело прямой линией от головы до пяток. Опуститесь, пока грудь не окажется у самого пола, и отожмитесь. Локти направлены под углом около 45 градусов к корпусу.',
            ],
        ],
    ],
    [
        'slug' => 'knee-push-up',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Knee Push-up',
                'description' => 'Start in a push-up position with your knees on the floor and your body straight from head to knees. Lower your chest to just above the floor, then push back up. Move on to full push-ups once you can do 15 repetitions.',
            ],
            'ru' => [
                'name' => 'Отжимания с колен',
                'description' => 'Примите упор лёжа с опорой на колени, тело прямое от головы до колен. Опуститесь, пока грудь не окажется у самого пола, и отожмитесь. Переходите к обычным отжиманиям, когда сможете выполнить 15 повторений.',
            ],
        ],
    ],
    [
        'slug' => 'incline-push-up',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Incline Push-up',
                'description' => 'Place your hands on a bench or a sturdy table and step back until your body is straight. Lower your chest to the edge, then push back up. The higher the support, the easier the exercise.',
            ],
            'ru' => [
                'name' => 'Отжимания от возвышения',
                'description' => 'Поставьте ладони на скамью или устойчивый стол и отойдите назад, чтобы тело стало прямым. Опустите грудь к краю опоры и отожмитесь. Чем выше опора, тем легче упражнение.',
            ],
        ],
    ],
    [
        'slug' => 'decline-push-up',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest', 'shoulders'],
        'translations' => [
            'en' => [
                'name' => 'Decline Push-up',
                'description' => 'Place your feet on a bench or chair and your hands on the floor slightly wider than your shoulders. Lower your chest to just above the floor, then push back up. Keep your hips from sagging.',
            ],
            'ru' => [
                'name' => 'Отжимания с ногами на возвышении',
                'description' => 'Поставьте стопы на скамью или стул, а ладони на пол чуть шире плеч. Опуститесь, пока грудь не окажется у самого пола, и отожмитесь. Не допускайте провисания таза.',
            ],
        ],
    ],
    [
        'slug' => 'chest-dip',
        'equipment' => 'dip-bars',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Chest Dip',
                'description' => 'Support yourself on the bars with your arms straight, then lean your torso forward. Lower yourself until your shoulders are level with your elbows, then push back up. The forward lean shifts the work to the chest.',
            ],
            'ru' => [
                'name' => 'Отжимания на брусьях на грудь',
                'description' => 'Примите упор на брусьях на прямых руках и наклоните корпус вперёд. Опуститесь, пока плечи не окажутся на уровне локтей, и отожмитесь. Наклон вперёд смещает нагрузку на грудные мышцы.',
            ],
        ],
    ],
    [
        'slug' => 'band-chest-press',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Band Chest Press',
                'description' => 'Anchor the band behind you at chest height, or loop it around your back, and hold an end in each hand. Press your hands forward until your arms are straight, then return slowly. Step further from the anchor to make it harder.',
            ],
            'ru' => [
                'name' => 'Жим от груди с эспандером',
                'description' => 'Закрепите эспандер позади себя на уровне груди или пропустите его за спиной и возьмите концы в руки. Выжмите руки вперёд до выпрямления и медленно вернитесь. Чтобы увеличить нагрузку, отойдите дальше от точки крепления.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-pullover',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['chest', 'back'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Pullover',
                'description' => 'Lie on a bench holding one dumbbell with both hands above your chest, arms slightly bent. Lower the dumbbell in an arc behind your head until you feel a stretch, then pull it back over your chest.',
            ],
            'ru' => [
                'name' => 'Пуловер с гантелью',
                'description' => 'Лягте на скамью, удерживая одну гантель обеими руками над грудью, руки слегка согнуты. Опустите гантель по дуге за голову до ощущения растяжения и верните её в положение над грудью.',
            ],
        ],
    ],
];
