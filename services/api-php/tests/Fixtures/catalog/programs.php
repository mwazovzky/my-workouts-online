<?php

use App\Enums\Weekday;

return [
    [
        'slug' => 'starter',
        'translations' => [
            'en' => ['name' => 'Starter', 'description' => 'Two strength days and one easy ride.'],
            'ru' => ['name' => 'Стартовая', 'description' => 'Два силовых дня и один лёгкий заезд.'],
        ],
        'schedule' => [
            ['weekday' => Weekday::Monday, 'template' => 'full-body'],
            ['weekday' => Weekday::Thursday, 'template' => 'full-body'],
            ['weekday' => Weekday::Saturday, 'template' => 'easy-spin'],
        ],
    ],
];
