<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseAdminTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'equipment_id' => Equipment::factory()->create()->id,
            'effort_type' => 'repetitions',
            'rest_time_seconds' => 90,
            'category_ids' => [],
            'translations' => [
                'en' => ['name' => 'Bench Press', 'description' => 'Chest exercise.'],
                'ru' => ['name' => 'Жим лёжа', 'description' => 'Упражнение на грудь.'],
            ],
        ], $overrides);
    }

    #[Test]
    public function non_admin_cannot_list_exercises(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/exercises')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_list_exercises_with_categories(): void
    {
        $admin = User::factory()->admin()->create();
        Exercise::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/exercises')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'name', 'description', 'equipment_id', 'effort_type',
                    'rest_time_seconds', 'category_ids', 'categories',
                    'translations' => ['name' => ['en', 'ru'], 'description' => ['en', 'ru']],
                ]],
            ]);
    }

    #[Test]
    public function admin_can_show_exercise_with_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(2)->create();
        $exercise = Exercise::factory()->create();
        $exercise->categories()->sync($categories->pluck('id')->all());

        $this->actingAs($admin)->getJson("/api/v1/admin/exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $exercise->id)
            ->assertJsonCount(2, 'data.category_ids')
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'description', 'equipment_id', 'effort_type',
                    'rest_time_seconds', 'category_ids', 'categories',
                    'translations' => ['name' => ['en', 'ru'], 'description' => ['en', 'ru']],
                ],
            ]);
    }

    #[Test]
    public function non_admin_cannot_show_exercise(): void
    {
        $exercise = Exercise::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/admin/exercises/{$exercise->id}")
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_create_exercise_with_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(2)->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/exercises', $this->validPayload([
            'category_ids' => $categories->pluck('id')->all(),
        ]));

        $response->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('exercises', [
            'id' => $id, 'effort_type' => 'repetitions', 'rest_time_seconds' => 90,
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'exercise', 'translatable_id' => $id,
            'locale' => 'ru', 'field' => 'name', 'value' => 'Жим лёжа',
        ]);
        $this->assertDatabaseCount('category_exercise', 2);
    }

    #[Test]
    public function admin_can_create_exercise_with_blank_russian_description(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/exercises', $this->validPayload([
            'rest_time_seconds' => 0,
            'translations' => [
                'en' => ['name' => 'Bicycle Training', 'description' => 'Cycling for time.'],
                'ru' => ['name' => 'Велотренировка', 'description' => null],
            ],
        ]));

        $response->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('exercises', ['id' => $id, 'rest_time_seconds' => 0]);
        $this->assertDatabaseMissing('translations', [
            'translatable_id' => $id, 'locale' => 'ru', 'field' => 'description',
        ]);
    }

    #[Test]
    public function admin_can_create_exercise_without_rest_time(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/exercises', $this->validPayload([
            'rest_time_seconds' => null,
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.rest_time_seconds', null);
        $this->assertDatabaseHas('exercises', [
            'id' => $response->json('data.id'),
            'rest_time_seconds' => null,
        ]);
    }

    #[Test]
    public function non_admin_cannot_create_exercise(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/exercises', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('exercises', 0);
    }

    #[Test]
    public function create_requires_valid_equipment(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/exercises', $this->validPayload([
            'equipment_id' => 99999,
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('equipment_id');
    }

    #[Test]
    public function create_rejects_invalid_effort_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/exercises', $this->validPayload([
            'effort_type' => 'jumps',
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('effort_type');
    }

    #[Test]
    public function create_requires_english_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/exercises', $this->validPayload([
            'translations' => ['en' => ['name' => '', 'description' => '']],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('translations.en.name');
    }

    #[Test]
    public function admin_can_update_exercise_and_resync_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $exercise = Exercise::factory()->create();
        $original = Category::factory()->create();
        $exercise->categories()->attach($original->id);
        $replacement = Category::factory()->create();

        $this->actingAs($admin)->putJson("/api/v1/admin/exercises/{$exercise->id}", $this->validPayload([
            'equipment_id' => $exercise->equipment_id,
            'category_ids' => [$replacement->id],
        ]))->assertOk();

        $this->assertDatabaseHas('category_exercise', [
            'exercise_id' => $exercise->id, 'category_id' => $replacement->id,
        ]);
        $this->assertDatabaseMissing('category_exercise', [
            'exercise_id' => $exercise->id, 'category_id' => $original->id,
        ]);
    }

    #[Test]
    public function admin_can_delete_exercise_which_soft_deletes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/exercises/{$exercise->id}")->assertNoContent();

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
    }

    #[Test]
    public function a_retired_exercise_is_excluded_from_the_admin_list(): void
    {
        $admin = User::factory()->admin()->create();
        $kept = Exercise::factory()->create();
        $retired = Exercise::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/exercises/{$retired->id}")->assertNoContent();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/exercises');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($kept->id, $response->json('data.0.id'));
    }

    #[Test]
    public function retiring_an_in_use_exercise_preserves_workout_history_and_name(): void
    {
        $admin = User::factory()->admin()->create();
        $exercise = Exercise::createWithTranslations(
            ['en' => ['name' => 'Bicycle Training', 'description' => 'Ride for time.']],
            ['equipment_id' => Equipment::factory()->create()->id, 'effort_type' => 'duration'],
        );

        $workout = \App\Models\Workout::factory()->create(['status' => 'in_progress']);
        $activity = \App\Models\Activity::factory()->for($workout, 'workout')->create([
            'exercise_id' => $exercise->id, 'order' => 1,
        ]);
        $set = \App\Models\Set::factory()->for($activity, 'activity')->create(['order' => 1]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/exercises/{$exercise->id}")->assertNoContent();

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('sets', ['id' => $set->id]);

        // The activity still resolves the retired exercise's name (withTrashed + kept translations).
        $this->assertSame('Bicycle Training', $activity->fresh()->exercise->name);
    }
}
