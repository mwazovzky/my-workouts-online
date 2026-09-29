<?php

use App\Enums\EffortType;

return [
    [
        'slug' => 'dumbbell-lateral-raise',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 60,
        'categories' => ['shoulders'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Lateral Raise',
                'description' => 'Stand with a dumbbell in each hand at your sides, elbows slightly bent. Raise your arms out to the sides until they are level with your shoulders, then lower them slowly. Use a light weight and do not swing.',
            ],
            'ru' => [
                'name' => 'Разведение гантелей в стороны',
                'description' => 'Встаньте прямо, гантели опущены вдоль тела, локти слегка согнуты. Поднимите руки через стороны до уровня плеч и медленно опустите. Берите небольшой вес и не помогайте себе раскачкой.',
            ],
        ],
        'previous_names' => ['Lateral Raise'],
    ],
    [
        'slug' => 'dumbbell-shoulder-press',
        'equipment' => 'dumbbell',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 120,
        'categories' => ['shoulders', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Dumbbell Shoulder Press',
                'description' => 'Sit upright with a dumbbell in each hand at shoulder height, palms facing forward. Press the dumbbells overhead until your arms are straight, then lower them back to your shoulders. Keep your lower back against the seat.',
            ],
            'ru' => [
                'name' => 'Жим гантелей сидя',
                'description' => 'Сядьте прямо, гантели у плеч, ладони направлены вперёд. Выжмите гантели над головой до выпрямления рук и опустите обратно к плечам. Поясница прижата к спинке.',
            ],
        ],
        'previous_names' => ['Shoulder Press. Dumbbell'],
    ],
    [
        'slug' => 'machine-shoulder-press',
        'equipment' => 'shoulder-press-machine',
        'effort_type' => EffortType::Repetitions,
        'rest_time_seconds' => 90,
        'categories' => ['shoulders', 'triceps'],
        'translations' => [
            'en' => [
                'name' => 'Machine Shoulder Press',
                'description' => 'Set the seat so the handles are level with your shoulders. Press the handles up until your arms are almost straight, then lower them slowly to the start. Keep your back against the pad.',
            ],
            'ru' => [
                'name' => 'Жим от плеч в тренажёре',
                'description' => 'Отрегулируйте сиденье так, чтобы рукояти были на уровне плеч. Выжмите рукояти вверх почти до выпрямления рук и медленно опустите в исходное положение. Спина прижата к спинке.',
            ],
        ],
        'previous_names' => ['Shoulder Press. Machine'],
    ],
];
