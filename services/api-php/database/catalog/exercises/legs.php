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
    [
        'slug' => 'barbell-front-squat',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 180,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Front Squat',
                'description' => 'Rest the bar on the front of your shoulders with your elbows high. Squat until your thighs are at least parallel to the floor, keeping your torso upright, then stand back up. Do not let your elbows drop.',
            ],
            'ru' => [
                'name' => 'Фронтальные приседания',
                'description' => 'Расположите гриф на передней части плеч, локти подняты высоко. Присядьте, пока бёдра не станут как минимум параллельны полу, сохраняя корпус вертикальным, и встаньте. Не опускайте локти.',
            ],
        ],
    ],
    [
        'slug' => 'hack-squat',
        'equipment' => 'hack-squat-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Hack Squat',
                'description' => 'Stand on the platform with your back against the pad and your shoulders under the supports. Release the safety, lower yourself until your thighs are parallel to the platform, then push back up without locking your knees.',
            ],
            'ru' => [
                'name' => 'Гакк-приседания',
                'description' => 'Встаньте на платформу, прижав спину к спинке, плечи под упорами. Снимите тренажёр с фиксаторов, опуститесь до параллели бёдер с платформой и выжмите вес, не выпрямляя колени до упора.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-goblet-squat',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Goblet Squat',
                'description' => 'Hold one dumbbell upright against your chest with both hands. Squat between your knees until your thighs are parallel to the floor, keeping your chest up, then stand back up.',
            ],
            'ru' => [
                'name' => 'Кубковые приседания с гантелью',
                'description' => 'Удерживайте одну гантель вертикально у груди обеими руками. Присядьте, опуская таз между коленями до параллели бёдер с полом и сохраняя грудь расправленной, и встаньте.',
            ],
        ],
    ],
    [
        'slug' => 'kettlebell-goblet-squat',
        'equipment' => 'kettlebell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Kettlebell Goblet Squat',
                'description' => 'Hold the kettlebell by the sides of its handle against your chest. Squat between your knees until your thighs are parallel to the floor, keeping your chest up, then stand back up.',
            ],
            'ru' => [
                'name' => 'Кубковые приседания с гирей',
                'description' => 'Удерживайте гирю за боковые части ручки у груди. Присядьте, опуская таз между коленями до параллели бёдер с полом и сохраняя грудь расправленной, и встаньте.',
            ],
        ],
    ],
    [
        'slug' => 'bodyweight-squat',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Bodyweight Squat',
                'description' => 'Stand with your feet shoulder-width apart and your arms in front of you. Sit back and down until your thighs are parallel to the floor, keeping your heels on the ground, then stand back up.',
            ],
            'ru' => [
                'name' => 'Приседания без веса',
                'description' => 'Встаньте, ноги на ширине плеч, руки перед собой. Опуститесь, отводя таз назад, до параллели бёдер с полом, не отрывая пятки, и встаньте.',
            ],
        ],
    ],
    [
        'slug' => 'jump-squat',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Jump Squat',
                'description' => 'Squat until your thighs are parallel to the floor, then jump straight up as high as you can. Land softly on the balls of your feet and go straight into the next squat.',
            ],
            'ru' => [
                'name' => 'Приседания с выпрыгиванием',
                'description' => 'Присядьте до параллели бёдер с полом и выпрыгните вертикально вверх как можно выше. Мягко приземлитесь на носки и сразу переходите к следующему приседанию.',
            ],
        ],
    ],
    [
        'slug' => 'band-squat',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Band Squat',
                'description' => 'Stand on the band with your feet shoulder-width apart and hold the ends at your shoulders. Squat until your thighs are parallel to the floor, then stand back up against the pull of the band.',
            ],
            'ru' => [
                'name' => 'Приседания с эспандером',
                'description' => 'Встаньте на эспандер, ноги на ширине плеч, концы удерживайте у плеч. Присядьте до параллели бёдер с полом и встаньте, преодолевая сопротивление эспандера.',
            ],
        ],
    ],
    [
        'slug' => 'wall-sit',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Duration,
        'rest_time_seconds' => 60,
        'categories' => ['legs'],
        'translations' => [
            'en' => [
                'name' => 'Wall Sit',
                'description' => 'Lean your back against a wall and slide down until your thighs are parallel to the floor, knees above your ankles. Hold the position for the set time with your back flat against the wall.',
            ],
            'ru' => [
                'name' => 'Стульчик у стены',
                'description' => 'Прислонитесь спиной к стене и опуститесь, пока бёдра не станут параллельны полу, колени над голеностопами. Удерживайте положение заданное время, спина прижата к стене.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-lunge',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Lunge',
                'description' => 'Stand with a dumbbell in each hand. Step forward and lower yourself until both knees are bent to about 90 degrees, then push off your front foot to return. Alternate legs.',
            ],
            'ru' => [
                'name' => 'Выпады с гантелями',
                'description' => 'Встаньте прямо, гантели в опущенных руках. Сделайте шаг вперёд и опуститесь до сгибания обоих коленей примерно под 90 градусов, затем оттолкнитесь передней ногой и вернитесь. Чередуйте ноги.',
            ],
        ],
    ],
    [
        'slug' => 'walking-lunge',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Walking Lunge',
                'description' => 'Step forward and lower yourself until both knees are bent to about 90 degrees. Push off your back foot and step straight into the next lunge with the other leg. Keep your torso upright.',
            ],
            'ru' => [
                'name' => 'Выпады в ходьбе',
                'description' => 'Сделайте шаг вперёд и опуститесь до сгибания обоих коленей примерно под 90 градусов. Оттолкнитесь задней ногой и сразу шагните в следующий выпад другой ногой. Корпус остаётся вертикальным.',
            ],
        ],
    ],
    [
        'slug' => 'reverse-lunge',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Reverse Lunge',
                'description' => 'Step one foot back and lower yourself until both knees are bent to about 90 degrees. Push through your front heel to return to standing. Stepping back is easier on the knees than stepping forward.',
            ],
            'ru' => [
                'name' => 'Обратные выпады',
                'description' => 'Сделайте шаг назад и опуститесь до сгибания обоих коленей примерно под 90 градусов. Оттолкнитесь пяткой передней ноги и вернитесь в положение стоя. Шаг назад меньше нагружает колени, чем шаг вперёд.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-bulgarian-split-squat',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Bulgarian Split Squat',
                'description' => 'Stand in front of a bench with a dumbbell in each hand and rest the top of one foot on the bench behind you. Lower yourself until your front thigh is parallel to the floor, then stand back up. Finish all repetitions before changing legs.',
            ],
            'ru' => [
                'name' => 'Болгарские сплит-приседания с гантелями',
                'description' => 'Встаньте перед скамьёй с гантелями в руках и положите подъём одной стопы на скамью позади себя. Опуститесь до параллели переднего бедра с полом и встаньте. Выполните все повторения, затем смените ногу.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-step-up',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Step-up',
                'description' => 'Stand in front of a bench or box with a dumbbell in each hand. Step up with one foot and stand fully, then lower yourself back under control. Finish all repetitions before changing legs.',
            ],
            'ru' => [
                'name' => 'Зашагивания на платформу с гантелями',
                'description' => 'Встаньте перед скамьёй или платформой с гантелями в руках. Поставьте одну ногу на опору и полностью выпрямитесь, затем подконтрольно опуститесь. Выполните все повторения, затем смените ногу.',
            ],
        ],
    ],
    [
        'slug' => 'barbell-romanian-deadlift',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['legs', 'glutes', 'back'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Romanian Deadlift',
                'description' => 'Stand holding the bar in front of your thighs with your knees slightly bent. Push your hips back and lower the bar along your legs to mid-shin, keeping your back flat, then stand up by driving your hips forward. You should feel the stretch in the back of your thighs.',
            ],
            'ru' => [
                'name' => 'Румынская тяга со штангой',
                'description' => 'Встаньте прямо, штанга перед бёдрами, колени слегка согнуты. Отведите таз назад и опустите штангу вдоль ног до середины голени, сохраняя спину прямой, затем выпрямитесь, подавая таз вперёд. Растяжение должно ощущаться в задней поверхности бедра.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-romanian-deadlift',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['legs', 'glutes', 'back'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Romanian Deadlift',
                'description' => 'Stand with the dumbbells in front of your thighs and your knees slightly bent. Push your hips back and lower the dumbbells along your legs to mid-shin, keeping your back flat, then stand up by driving your hips forward.',
            ],
            'ru' => [
                'name' => 'Румынская тяга с гантелями',
                'description' => 'Встаньте прямо, гантели перед бёдрами, колени слегка согнуты. Отведите таз назад и опустите гантели вдоль ног до середины голени, сохраняя спину прямой, затем выпрямитесь, подавая таз вперёд.',
            ],
        ],
    ],
    [
        'slug' => 'band-deadlift',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['legs', 'glutes', 'back'],
        'translations' => [
            'en' => [
                'name' => 'Band Deadlift',
                'description' => 'Stand on the middle of the band with your feet hip-width apart and hold the ends beside your shins. With a flat back, stand up by driving your hips forward, then lower your hands slowly.',
            ],
            'ru' => [
                'name' => 'Становая тяга с эспандером',
                'description' => 'Встаньте на середину эспандера, ноги на ширине таза, концы удерживайте у голеней. С прямой спиной выпрямитесь, подавая таз вперёд, и медленно опустите руки.',
            ],
        ],
    ],
    [
        'slug' => 'barbell-hip-thrust',
        'equipment' => 'barbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['glutes', 'legs'],
        'translations' => [
            'en' => [
                'name' => 'Barbell Hip Thrust',
                'description' => 'Sit with your upper back against a bench and the bar across your hips, feet flat on the floor. Drive your hips up until your body is straight from shoulders to knees, squeeze your glutes, then lower your hips. Use a pad under the bar.',
            ],
            'ru' => [
                'name' => 'Ягодичный мост со штангой',
                'description' => 'Сядьте, опираясь верхней частью спины о скамью, штанга лежит на тазе, стопы на полу. Поднимите таз до прямой линии от плеч до колен, напрягите ягодицы и опустите таз. Подложите под гриф мягкую накладку.',
            ],
        ],
    ],
    [
        'slug' => 'glute-bridge',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['glutes'],
        'translations' => [
            'en' => [
                'name' => 'Glute Bridge',
                'description' => 'Lie on your back with your knees bent and your feet flat on the floor. Push through your heels to raise your hips until your body is straight from shoulders to knees, squeeze your glutes, then lower your hips.',
            ],
            'ru' => [
                'name' => 'Ягодичный мост',
                'description' => 'Лягте на спину, согнув колени, стопы на полу. Упираясь пятками, поднимите таз до прямой линии от плеч до колен, напрягите ягодицы и опустите таз.',
            ],
        ],
    ],
    [
        'slug' => 'single-leg-glute-bridge',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['glutes'],
        'translations' => [
            'en' => [
                'name' => 'Single-Leg Glute Bridge',
                'description' => 'Lie on your back with one knee bent and the other leg straight in the air. Push through your heel to raise your hips until your body is straight, then lower them. Finish all repetitions before changing legs.',
            ],
            'ru' => [
                'name' => 'Ягодичный мост на одной ноге',
                'description' => 'Лягте на спину, одна нога согнута, другая выпрямлена и поднята. Упираясь пяткой, поднимите таз до прямой линии тела и опустите. Выполните все повторения, затем смените ногу.',
            ],
        ],
    ],
    [
        'slug' => 'machine-hip-abduction',
        'equipment' => 'hip-abduction-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['glutes'],
        'translations' => [
            'en' => [
                'name' => 'Machine Hip Abduction',
                'description' => 'Sit with your back against the pad and the outside of your knees against the pads. Push your legs apart as far as you comfortably can, pause, then bring them back together slowly.',
            ],
            'ru' => [
                'name' => 'Разведение ног в тренажёре',
                'description' => 'Сядьте, прижав спину к спинке, внешняя сторона коленей упирается в валики. Разведите ноги настолько, насколько это комфортно, задержитесь и медленно сведите их.',
            ],
        ],
    ],
    [
        'slug' => 'cable-glute-kickback',
        'equipment' => 'cable-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['glutes'],
        'translations' => [
            'en' => [
                'name' => 'Cable Glute Kickback',
                'description' => 'Attach the ankle strap to the low pulley and face the machine, holding it for balance. Move your leg straight back by squeezing your glute, then return slowly. Do not arch your lower back.',
            ],
            'ru' => [
                'name' => 'Отведение ноги назад на блоке',
                'description' => 'Закрепите манжету нижнего блока на лодыжке и встаньте лицом к тренажёру, придерживаясь за него. Отведите ногу назад за счёт напряжения ягодицы и медленно вернитесь. Не прогибайтесь в пояснице.',
            ],
        ],
    ],
    [
        'slug' => 'band-lateral-walk',
        'equipment' => 'resistance-band',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['glutes'],
        'translations' => [
            'en' => [
                'name' => 'Band Lateral Walk',
                'description' => 'Place a loop band around your legs just above your knees and lower into a quarter squat. Take small steps to the side while keeping the band tight, then walk back the other way. Count each step as one repetition.',
            ],
            'ru' => [
                'name' => 'Шаги в сторону с эспандером',
                'description' => 'Наденьте эспандер-петлю на ноги чуть выше коленей и слегка присядьте. Делайте небольшие шаги в сторону, сохраняя натяжение эспандера, затем идите в обратном направлении. Каждый шаг считается одним повторением.',
            ],
        ],
    ],
    [
        'slug' => 'bodyweight-calf-raise',
        'equipment' => 'bodyweight',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['calves'],
        'translations' => [
            'en' => [
                'name' => 'Bodyweight Calf Raise',
                'description' => 'Stand on the edge of a step with your heels hanging off, holding something for balance. Rise onto your toes as high as you can, pause, then lower your heels below the step. Use one leg at a time to make it harder.',
            ],
            'ru' => [
                'name' => 'Подъём на носки стоя',
                'description' => 'Встаньте на край ступеньки так, чтобы пятки свисали, и придерживайтесь за опору. Поднимитесь на носки как можно выше, задержитесь и опустите пятки ниже уровня ступеньки. Чтобы увеличить нагрузку, выполняйте упражнение на одной ноге.',
            ],
        ],
    ],
    [
        'slug' => 'dumbbell-calf-raise',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 45,
        'categories' => ['calves'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Calf Raise',
                'description' => 'Stand with a dumbbell in each hand and the balls of your feet on a step or plate. Rise onto your toes as high as you can, pause, then lower your heels for a full stretch.',
            ],
            'ru' => [
                'name' => 'Подъём на носки с гантелями',
                'description' => 'Встаньте носками на ступеньку или диск, гантели в опущенных руках. Поднимитесь на носки как можно выше, задержитесь и опустите пятки до полного растяжения икр.',
            ],
        ],
    ],
];
