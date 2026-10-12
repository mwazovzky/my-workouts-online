<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// Captures the Go catalog tests' golden files from the PHP API; see
// services/api-go/internal/catalog/adapters/http/testdata/README.md. It lives
// here, not in tests/, so the PHP suite never runs it.
class GoGoldenCaptureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function capture(): void
    {
        $user = User::factory()->create();

        $barbell = Equipment::createWithTranslations(['en' => ['name' => 'Barbell'], 'ru' => ['name' => 'Штанга']], ['difficulty_unit' => 'kilograms']);
        $bodyweight = Equipment::createWithTranslations(['en' => ['name' => 'Bodyweight'], 'ru' => ['name' => 'Собственный вес']], ['difficulty_unit' => 'none']);
        $dumbbell = Equipment::createWithTranslations(['en' => ['name' => 'Dumbbell']], ['difficulty_unit' => 'pounds']);

        $chest = Category::createWithTranslations(['en' => ['name' => 'Chest'], 'ru' => ['name' => 'Грудь']]);
        $legs = Category::createWithTranslations(['en' => ['name' => 'Legs'], 'ru' => ['name' => 'Ноги']]);
        $back = Category::createWithTranslations(['en' => ['name' => 'Back']]);

        $squat = Exercise::createWithTranslations(
            ['en' => ['name' => 'Squat', 'description' => 'Feet shoulder-width apart.'], 'ru' => ['name' => 'Приседания', 'description' => 'Ноги на ширине плеч.']],
            ['equipment_id' => $barbell->id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 120],
        );
        $squat->categories()->attach($legs->id);

        $bench = Exercise::createWithTranslations(
            ['en' => ['name' => 'bench press'], 'ru' => ['name' => 'Жим лёжа']],
            ['equipment_id' => $barbell->id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 90],
        );
        $bench->categories()->attach($chest->id);

        $pushUp = Exercise::createWithTranslations(
            ['en' => ['name' => 'Push-up', 'description' => 'Keep the body straight.'], 'ru' => ['name' => 'Отжимания']],
            ['equipment_id' => $bodyweight->id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 60],
        );
        $pushUp->categories()->attach($chest->id);

        $uprightRow = Exercise::createWithTranslations(
            ['en' => ['name' => 'Upright Row'], 'ru' => ['name' => 'Тяга к подбородку']],
            ['equipment_id' => $dumbbell->id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 90],
        );
        $uprightRow->categories()->attach($back->id);

        $plank = Exercise::createWithTranslations(
            ['en' => ['name' => 'Plank']],
            ['equipment_id' => $bodyweight->id, 'effort_type' => 'duration', 'rest_time_seconds' => null],
        );

        $deadlift = Exercise::createWithTranslations(
            ['en' => ['name' => 'Deadlift'], 'ru' => ['name' => 'Становая тяга']],
            ['equipment_id' => $barbell->id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 180],
        );
        $deadlift->categories()->attach($legs->id);
        $deadlift->categories()->attach($back->id);

        Exercise::createWithTranslations(
            ['en' => ['name' => 'Burpee'], 'ru' => ['name' => 'Бёрпи']],
            ['equipment_id' => $bodyweight->id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 30],
        );

        $uprightRow->delete();

        $fullBody = WorkoutTemplate::createWithTranslations([
            'en' => ['name' => 'Full Body A', 'description' => 'Squat, push-up, row.'],
            'ru' => ['name' => 'Всё тело А', 'description' => 'Присед, отжимания, тяга.'],
        ]);
        $core = WorkoutTemplate::createWithTranslations(['en' => ['name' => 'Core']]);

        $a1 = Activity::create(['workout_id' => $fullBody->id, 'workout_type' => 'workout_template', 'exercise_id' => $pushUp->id, 'order' => 2]);
        $a2 = Activity::create(['workout_id' => $fullBody->id, 'workout_type' => 'workout_template', 'exercise_id' => $squat->id, 'order' => 1]);
        $a3 = Activity::create(['workout_id' => $fullBody->id, 'workout_type' => 'workout_template', 'exercise_id' => $uprightRow->id, 'order' => 3]);
        $a4 = Activity::create(['workout_id' => $core->id, 'workout_type' => 'workout_template', 'exercise_id' => $plank->id, 'order' => 1]);

        Set::create(['activity_id' => $a1->id, 'order' => 2, 'effort_value' => 12, 'difficulty_value' => null, 'is_completed' => false]);
        Set::create(['activity_id' => $a1->id, 'order' => 1, 'effort_value' => 15, 'difficulty_value' => null, 'is_completed' => false]);
        Set::create(['activity_id' => $a2->id, 'order' => 1, 'effort_value' => 5, 'difficulty_value' => 100, 'is_completed' => false]);
        Set::create(['activity_id' => $a2->id, 'order' => 2, 'effort_value' => 5, 'difficulty_value' => 102.5, 'is_completed' => false]);
        Set::create(['activity_id' => $a3->id, 'order' => 1, 'effort_value' => 10, 'difficulty_value' => 20, 'is_completed' => false]);
        Set::create(['activity_id' => $a4->id, 'order' => 1, 'effort_value' => 60, 'difficulty_value' => null, 'is_completed' => false]);

        $strength = Program::createWithTranslations([
            'en' => ['name' => 'Strength', 'description' => 'Three sessions a week.'],
            'ru' => ['name' => 'Сила', 'description' => 'Три тренировки в неделю.'],
        ]);
        Program::createWithTranslations(['en' => ['name' => 'Cardio']]);

        $strength->workoutTemplates()->attach($fullBody->id, ['weekday' => 'thursday']);
        $strength->workoutTemplates()->attach($core->id, ['weekday' => 'wednesday']);
        $strength->workoutTemplates()->attach($fullBody->id, ['weekday' => 'monday']);

        $strength->users()->attach($user->id);

        $paths = [
            'programs' => '/api/v1/programs',
            'program-1' => '/api/v1/programs/1',
            'program-2' => '/api/v1/programs/2',
            'workout-template-1' => '/api/v1/workout-templates/1',
            'workout-template-2' => '/api/v1/workout-templates/2',
            'exercises' => '/api/v1/exercises',
        ];

        foreach ($paths as $name => $path) {
            foreach (['en', 'ru'] as $locale) {
                $response = $this->actingAs($user)->withHeader('Accept-Language', $locale)->getJson($path);
                $response->assertOk();
                file_put_contents(storage_path("golden/{$name}.{$locale}.json"), $response->getContent());
            }
        }
    }
}
