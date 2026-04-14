<?php

namespace Database\Seeders;

use App\Enums\EffortType;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            [
                'en' => ['name' => 'Bench Press', 'description' => 'Chest strength exercise using a barbell.'],
                'ru' => ['name' => 'Жим штанги лёжа', 'description' => 'Упражнение на грудь со штангой.'],
                'equipment' => 'Barbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Chest', 'Triceps'],
            ],
            [
                'en' => ['name' => 'Squat', 'description' => 'Leg strength exercise using a barbell.'],
                'ru' => ['name' => 'Приседания со штангой', 'description' => 'Упражнение на ноги со штангой.'],
                'equipment' => 'Barbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Legs'],
            ],
            [
                'en' => ['name' => 'Deadlift', 'description' => 'Full body strength exercise using a barbell.'],
                'ru' => ['name' => 'Становая тяга', 'description' => 'Базовое упражнение на всё тело со штангой.'],
                'equipment' => 'Barbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Back', 'Legs'],
            ],
            [
                'en' => ['name' => 'Dumbbell Curl', 'description' => 'Biceps exercise using dumbbells.'],
                'ru' => ['name' => 'Сгибание рук с гантелями', 'description' => 'Упражнение на бицепс с гантелями.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Biceps'],
            ],
            [
                'en' => ['name' => 'Barbell Curl', 'description' => 'Biceps exercise using a barbell.'],
                'ru' => ['name' => 'Сгибание рук со штангой', 'description' => 'Упражнение на бицепс со штангой.'],
                'equipment' => 'Barbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Biceps'],
            ],
            [
                'en' => ['name' => 'Triceps Extension with Dumbbell', 'description' => 'Triceps exercise using dumbbells.'],
                'ru' => ['name' => 'Разгибание рук с гантелью', 'description' => 'Упражнение на трицепс с гантелями.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Triceps'],
            ],
            [
                'en' => ['name' => 'Triceps Extension on High Pulley Machine', 'description' => 'Triceps exercise using high pulley machine.'],
                'ru' => ['name' => 'Разгибание рук на верхнем блоке', 'description' => 'Упражнение на трицепс на верхнем блоке.'],
                'equipment' => 'High Pulley Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Triceps'],
            ],
            [
                'en' => ['name' => 'Lat Pulldown', 'description' => 'Back exercise using lat pulldown machine.'],
                'ru' => ['name' => 'Тяга верхнего блока', 'description' => 'Упражнение на спину на тренажёре верхнего блока.'],
                'equipment' => 'Lat Pulldown Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Back'],
            ],
            [
                'en' => ['name' => 'Leg Press', 'description' => 'Leg exercise using leg press machine.'],
                'ru' => ['name' => 'Жим ногами', 'description' => 'Упражнение на ноги на тренажёре жим ногами.'],
                'equipment' => 'Leg Press Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Legs'],
            ],
            [
                'en' => ['name' => 'Chest Press', 'description' => 'Chest exercise using chest press machine.'],
                'ru' => ['name' => 'Жим от груди в тренажёре', 'description' => 'Упражнение на грудь на тренажёре жим от груди.'],
                'equipment' => 'Chest Press Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Chest'],
            ],
            [
                'en' => ['name' => 'Pull-up', 'description' => 'Back and arms exercise using pull-up bar.'],
                'ru' => ['name' => 'Подтягивания', 'description' => 'Упражнение на спину и руки на турнике.'],
                'equipment' => 'Pull-up Bar',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Back', 'Biceps'],
            ],
            [
                'en' => ['name' => 'Kettlebell Swing', 'description' => 'Full body exercise using kettlebell.'],
                'ru' => ['name' => 'Махи гирей', 'description' => 'Упражнение на всё тело с гирей.'],
                'equipment' => 'Kettlebell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Legs', 'Back', 'Shoulders'],
            ],
            [
                'en' => ['name' => 'Incline Dumbbell Press', 'description' => 'Incline chest exercise using dumbbells.'],
                'ru' => ['name' => 'Жим гантелей на наклонной скамье', 'description' => 'Упражнение на верхнюю часть груди с гантелями.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Chest', 'Shoulders'],
            ],
            [
                'en' => ['name' => 'Lateral Raise', 'description' => 'Shoulder isolation exercise.'],
                'ru' => ['name' => 'Разведение гантелей в стороны', 'description' => 'Изолирующее упражнение на плечи.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Shoulders'],
            ],
            [
                'en' => ['name' => 'Chest Fly', 'description' => 'Chest isolation exercise using chest press machine.'],
                'ru' => ['name' => 'Разведение рук на тренажёре', 'description' => 'Изолирующее упражнение на грудь на тренажёре.'],
                'equipment' => 'Chest Press Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Chest'],
            ],
            [
                'en' => ['name' => 'Seated Row', 'description' => 'Back exercise using seated row machine.'],
                'ru' => ['name' => 'Тяга сидя', 'description' => 'Упражнение на спину на тренажёре тяги сидя.'],
                'equipment' => 'Seated Row Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Back'],
            ],
            [
                'en' => ['name' => 'Hammer Curl', 'description' => 'Biceps exercise using dumbbells.'],
                'ru' => ['name' => 'Молотковые сгибания', 'description' => 'Упражнение на бицепс с гантелями.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Biceps'],
            ],
            [
                'en' => ['name' => 'Leg Extension', 'description' => 'Quadriceps isolation exercise.'],
                'ru' => ['name' => 'Разгибание ног', 'description' => 'Изолирующее упражнение на квадрицепсы.'],
                'equipment' => 'Leg Extension Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Legs'],
            ],
            [
                'en' => ['name' => 'Leg Curl', 'description' => 'Hamstring isolation exercise.'],
                'ru' => ['name' => 'Сгибание ног', 'description' => 'Изолирующее упражнение на бицепс бедра.'],
                'equipment' => 'Leg Curl Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Legs'],
            ],
            [
                'en' => ['name' => 'Calf Raise', 'description' => 'Calf exercise using calf raise machine.'],
                'ru' => ['name' => 'Подъём на носки', 'description' => 'Упражнение на икры на тренажёре.'],
                'equipment' => 'Calf Raise Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Legs'],
            ],
            [
                'en' => ['name' => 'Plank', 'description' => 'Core stability exercise.'],
                'ru' => ['name' => 'Планка', 'description' => 'Упражнение на стабилизацию корпуса.'],
                'equipment' => 'Bodyweight',
                'effort_type' => EffortType::Duration,
                'categories' => ['Abs'],
            ],
            [
                'en' => ['name' => 'Crunches', 'description' => 'Core exercise performed without equipment.'],
                'ru' => ['name' => 'Скручивания', 'description' => 'Упражнение на пресс без оборудования.'],
                'equipment' => 'Bodyweight',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Abs'],
            ],
            [
                'en' => ['name' => 'Shoulder Press. Dumbbell', 'description' => 'Shoulder strength exercise using dumbbells.'],
                'ru' => ['name' => 'Жим гантелей сидя', 'description' => 'Жим на плечи с гантелями.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Shoulders'],
            ],
            [
                'en' => ['name' => 'Shoulder Press. Machine', 'description' => 'Shoulder strength exercise using machine.'],
                'ru' => ['name' => 'Жим от плеч в тренажёре', 'description' => 'Жим на плечи в тренажёре.'],
                'equipment' => 'Shoulder Press Machine',
                'effort_type' => EffortType::Repetitions,
                'categories' => ['Shoulders'],
            ],
            [
                'en' => ['name' => "Farmer's Walk", 'description' => 'Carry heavy weights for time.'],
                'ru' => ['name' => 'Прогулка фермера', 'description' => 'Перенос тяжёлых весов на время.'],
                'equipment' => 'Dumbbell',
                'effort_type' => EffortType::Duration,
                'categories' => ['Back', 'Legs'],
            ],
        ];

        foreach ($exercises as $item) {
            $equipment = Equipment::whereTranslated('name', $item['equipment'], 'en')->firstOrFail();

            $exercise = Exercise::firstOrCreateWithTranslations(
                ['en' => $item['en'], 'ru' => $item['ru']],
                [
                    'equipment_id' => $equipment->id,
                    'effort_type' => $item['effort_type'],
                ],
            );

            $categoryIds = Category::whereTranslatedIn('name', $item['categories'], 'en')
                ->pluck('id')
                ->toArray();

            $exercise->categories()->sync($categoryIds);
        }
    }
}
