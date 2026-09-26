<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\WorkoutTemplate;
use Illuminate\Database\Seeder;

class CyclingProgramSeeder extends Seeder
{
    private const PROGRAM_NAME_EN = 'Cycling — Beginner (Week 1)';

    /**
     * Seed a heart-rate-zone cycling program (Beginner, week 1) as a
     * self-contained example: its own equipment, categories, exercises,
     * workout templates, and the weekly program.
     *
     * Durations are stored in seconds (effort_type = duration); difficulty is a
     * heart-rate zone (1-5). Idempotent: skips if the program already exists.
     */
    public function run(): void
    {
        if (Program::whereTranslated('name', self::PROGRAM_NAME_EN, 'en')->exists()) {
            return;
        }

        $bike = Equipment::createWithTranslations(
            ['en' => ['name' => 'Indoor Bike'], 'ru' => ['name' => 'Велотренажёр']],
            ['difficulty_unit' => 'heart_rate_zone'],
        );

        $categories = $this->seedCategories();
        $exercises = $this->seedExercises($bike, $categories);

        // Beginner week 1. Tuesday and Thursday share the same session.
        $recoverySpin = $this->makeTemplate(
            [
                'en' => ['name' => 'Recovery Spin', 'description' => '30 minutes in Zone 1 on flat terrain at an easy, steady pace.'],
                'ru' => ['name' => 'Восстановительный заезд', 'description' => '30 минут в Зоне 1 на равнинной местности в спокойном ровном темпе.'],
            ],
            [
                ['exercise' => $exercises['recovery'], 'sets' => [['effort' => 1800, 'zone' => 1]]],
            ],
        );

        $enduranceShortDrill = $this->makeTemplate(
            [
                'en' => ['name' => 'Endurance + FastPedal (short)', 'description' => '45 minutes in Zone 2 with a 5-minute FastPedal drill on flat terrain.'],
                'ru' => ['name' => 'Выносливость + FastPedal (коротко)', 'description' => '45 минут в Зоне 2 с 5-минутной отработкой FastPedal на равнинной местности.'],
            ],
            [
                ['exercise' => $exercises['endurance'], 'sets' => [['effort' => 2700, 'zone' => 2]]],
                ['exercise' => $exercises['fast_pedal'], 'sets' => [['effort' => 300, 'zone' => null]]],
            ],
        );

        $steadyEndurance = $this->makeTemplate(
            [
                'en' => ['name' => 'Steady Endurance', 'description' => '1 hour in Zone 2 on flat terrain; hold 80–85 rpm throughout.'],
                'ru' => ['name' => 'Ровная выносливость', 'description' => '1 час в Зоне 2 на равнинной местности; держите 80–85 об/мин на всей тренировке.'],
            ],
            [
                ['exercise' => $exercises['endurance'], 'sets' => [['effort' => 3600, 'zone' => 2]]],
            ],
        );

        $enduranceLongDrill = $this->makeTemplate(
            [
                'en' => ['name' => 'Endurance + FastPedal (long)', 'description' => '1 hour in Zone 2 with a 10-minute FastPedal drill on flat terrain.'],
                'ru' => ['name' => 'Выносливость + FastPedal (длинно)', 'description' => '1 час в Зоне 2 с 10-минутной отработкой FastPedal на равнинной местности.'],
            ],
            [
                ['exercise' => $exercises['endurance'], 'sets' => [['effort' => 3600, 'zone' => 2]]],
                ['exercise' => $exercises['fast_pedal'], 'sets' => [['effort' => 600, 'zone' => null]]],
            ],
        );

        $hillEndurance = $this->makeTemplate(
            [
                'en' => ['name' => 'Hill Endurance', 'description' => '1.5 hours in Zone 2 on hilly terrain; try to stay seated on the climbs.'],
                'ru' => ['name' => 'Выносливость на холмах', 'description' => '1,5 часа в Зоне 2 на холмистой местности; старайтесь не вставать с седла на подъёмах.'],
            ],
            [
                ['exercise' => $exercises['endurance'], 'sets' => [['effort' => 5400, 'zone' => 2]]],
            ],
        );

        $program = Program::createWithTranslations([
            'en' => ['name' => self::PROGRAM_NAME_EN, 'description' => 'A seven-day beginner cycling week built around heart-rate zones.'],
            'ru' => ['name' => 'Велотренировки — Начальный уровень (1 неделя)', 'description' => 'Недельная программа для начинающих велосипедистов на основе зон пульса.'],
        ]);

        $schedule = [
            'Monday' => $recoverySpin,
            'Tuesday' => $enduranceShortDrill,
            'Wednesday' => $steadyEndurance,
            'Thursday' => $enduranceShortDrill,
            // Friday is a rest day — no template.
            'Saturday' => $enduranceLongDrill,
            'Sunday' => $hillEndurance,
        ];

        foreach ($schedule as $weekday => $template) {
            $program->workoutTemplates()->attach($template->id, ['weekday' => $weekday]);
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $definitions = [
            'cycling' => ['en' => 'Cycling', 'ru' => 'Велоспорт'],
            'endurance' => ['en' => 'Endurance', 'ru' => 'Выносливость'],
            'intervals' => ['en' => 'Intervals', 'ru' => 'Интервалы'],
            'recovery' => ['en' => 'Recovery', 'ru' => 'Восстановление'],
        ];

        $categories = [];
        foreach ($definitions as $key => $names) {
            $categories[$key] = Category::createWithTranslations([
                'en' => ['name' => $names['en']],
                'ru' => ['name' => $names['ru']],
            ]);
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     * @return array<string, Exercise>
     */
    private function seedExercises(Equipment $bike, array $categories): array
    {
        $definitions = [
            'endurance' => [
                'en' => ['name' => 'Endurance Ride', 'description' => 'Steady aerobic ride at a controlled heart-rate zone.'],
                'ru' => ['name' => 'Заезд на выносливость', 'description' => 'Ровный аэробный заезд в контролируемой зоне пульса.'],
                'rest' => null,
                'categories' => ['cycling', 'endurance'],
            ],
            'recovery' => [
                'en' => ['name' => 'Recovery Ride', 'description' => 'Easy Zone 1 spin to promote recovery.'],
                'ru' => ['name' => 'Восстановительный заезд', 'description' => 'Лёгкое вращение в Зоне 1 для восстановления.'],
                'rest' => null,
                'categories' => ['cycling', 'recovery'],
            ],
            'fast_pedal' => [
                'en' => ['name' => 'FastPedal', 'description' => 'High-cadence drill (100+ rpm) to build pedaling efficiency.'],
                'ru' => ['name' => 'FastPedal', 'description' => 'Высококадансная отработка (100+ об/мин) для эффективности педалирования.'],
                'rest' => null,
                'categories' => ['cycling'],
            ],
            'tempo' => [
                'en' => ['name' => 'Tempo', 'description' => 'Sustained tempo effort at the top of Zone 2.'],
                'ru' => ['name' => 'Темпо', 'description' => 'Продолжительное темповое усилие в верхней части Зоны 2.'],
                'rest' => null,
                'categories' => ['cycling', 'endurance'],
            ],
            'flat_sprint' => [
                'en' => ['name' => 'FlatSprint', 'description' => 'All-out 10-second sprints on flat terrain.'],
                'ru' => ['name' => 'FlatSprint', 'description' => 'Максимальные 10-секундные ускорения на равнинной местности.'],
                'rest' => 300,
                'categories' => ['cycling', 'intervals'],
            ],
            'power_interval' => [
                'en' => ['name' => 'PowerInterval', 'description' => 'Hard 3-minute intervals with recovery between efforts.'],
                'ru' => ['name' => 'PowerInterval', 'description' => 'Жёсткие 3-минутные интервалы с восстановлением между усилиями.'],
                'rest' => 180,
                'categories' => ['cycling', 'intervals'],
            ],
        ];

        $exercises = [];
        foreach ($definitions as $key => $definition) {
            $exercise = Exercise::createWithTranslations(
                ['en' => $definition['en'], 'ru' => $definition['ru']],
                [
                    'equipment_id' => $bike->id,
                    'effort_type' => 'duration',
                    'rest_time_seconds' => $definition['rest'],
                ],
            );

            $categoryIds = array_map(fn (string $category) => $categories[$category]->id, $definition['categories']);
            $exercise->categories()->sync($categoryIds);

            $exercises[$key] = $exercise;
        }

        return $exercises;
    }

    /**
     * @param  array<string, array<string, string>>  $translations
     * @param  array<int, array{exercise: Exercise, sets: array<int, array{effort: int, zone: ?int}>}>  $activities
     */
    private function makeTemplate(array $translations, array $activities): WorkoutTemplate
    {
        $template = WorkoutTemplate::createWithTranslations($translations);

        foreach ($activities as $activityIndex => $activityData) {
            $activity = $template->activities()->create([
                'exercise_id' => $activityData['exercise']->id,
                'order' => $activityIndex + 1,
            ]);

            foreach ($activityData['sets'] as $setIndex => $set) {
                $activity->sets()->create([
                    'order' => $setIndex + 1,
                    'effort_value' => $set['effort'],
                    'difficulty_value' => $set['zone'],
                ]);
            }
        }

        return $template;
    }
}
