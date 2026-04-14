<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranslatedResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workout_index_returns_russian_status_label_for_russian_user(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $template = WorkoutTemplate::factory()->create();

        Workout::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/workouts');

        $response->assertOk();

        $workouts = $response->json('data');
        $this->assertNotEmpty($workouts);
        $this->assertEquals('В процессе', $workouts[0]['status_label']);
        $this->assertEquals('in_progress', $workouts[0]['status']);
    }

    #[Test]
    public function workout_index_returns_english_status_label_for_english_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $template = WorkoutTemplate::factory()->create();

        Workout::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/workouts');

        $response->assertOk();

        $workouts = $response->json('data');
        $this->assertNotEmpty($workouts);
        $this->assertEquals('Completed', $workouts[0]['status_label']);
    }

    #[Test]
    public function program_show_returns_russian_content_for_russian_user(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $program = Program::createWithTranslations([
            'en' => ['name' => 'Beginner', 'description' => 'A beginner program.'],
            'ru' => ['name' => 'Начинающий', 'description' => 'Программа для начинающих.'],
        ]);

        $program->users()->attach($user->id);

        $response = $this->actingAs($user)->getJson("/api/v1/programs/{$program->id}");

        $response->assertOk();

        $this->assertEquals('Начинающий', $response->json('data.name'));
        $this->assertEquals('Программа для начинающих.', $response->json('data.description'));
    }

    #[Test]
    public function workout_show_returns_translated_exercise_names(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $equipment = Equipment::createWithTranslations([
            'en' => ['name' => 'Barbell', 'unit' => 'kg'],
            'ru' => ['name' => 'Штанга', 'unit' => 'кг'],
        ]);

        $category = Category::createWithTranslations([
            'en' => ['name' => 'Chest'],
            'ru' => ['name' => 'Грудь'],
        ]);

        $exercise = Exercise::createWithTranslations([
            'en' => ['name' => 'Bench Press', 'description' => 'Chest exercise.'],
            'ru' => ['name' => 'Жим лёжа', 'description' => 'Упражнение на грудь.'],
        ], [
            'equipment_id' => $equipment->id,
            'rest_time_seconds' => 90,
        ]);

        $exercise->categories()->attach($category->id);

        $template = WorkoutTemplate::factory()->create();

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template->id,
            'status' => 'in_progress',
        ]);

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        Set::factory()->create([
            'activity_id' => $activity->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/workouts/{$workout->id}");

        $response->assertOk();

        $this->assertEquals('В процессе', $response->json('data.status_label'));
    }
}
