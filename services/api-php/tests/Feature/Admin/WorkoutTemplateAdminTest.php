<?php

namespace Tests\Feature\Admin;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        $exercise = Exercise::factory()->create();

        return array_merge([
            'translations' => [
                'en' => ['name' => 'Full Body', 'description' => 'A full body session.'],
                'ru' => ['name' => 'Всё тело', 'description' => 'Тренировка на всё тело.'],
            ],
            'activities' => [
                [
                    'exercise_id' => $exercise->id,
                    'order' => 1,
                    'sets' => [
                        ['order' => 1, 'effort_value' => 10, 'difficulty_value' => 50],
                        ['order' => 2, 'effort_value' => 8, 'difficulty_value' => 60],
                    ],
                ],
            ],
        ], $overrides);
    }

    #[Test]
    public function non_admin_cannot_list_templates(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/workout-templates')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_list_templates_with_activity_counts(): void
    {
        $admin = User::factory()->admin()->create();
        WorkoutTemplate::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/workout-templates')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'activities_count', 'translations' => ['name' => ['en', 'ru']]]],
            ]);
    }

    #[Test]
    public function admin_can_create_template_with_activities_and_sets(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/workout-templates', $this->validPayload());

        $response->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'workout_template', 'translatable_id' => $id,
            'locale' => 'ru', 'field' => 'name', 'value' => 'Всё тело',
        ]);
        $this->assertDatabaseHas('activities', [
            'workout_type' => 'workout_template', 'workout_id' => $id, 'order' => 1,
        ]);
        $this->assertDatabaseCount('sets', 2);
        $this->assertDatabaseHas('sets', ['order' => 2, 'effort_value' => 8, 'difficulty_value' => 60]);
    }

    #[Test]
    public function non_admin_cannot_create_template(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/workout-templates', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('activities', 0);
    }

    #[Test]
    public function admin_can_show_template_with_full_structure(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)
            ->postJson('/api/v1/admin/workout-templates', $this->validPayload())
            ->json('data.id');

        $this->actingAs($admin)->getJson("/api/v1/admin/workout-templates/{$id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'translations' => ['name' => ['en', 'ru'], 'description' => ['en', 'ru']],
                    'activities' => [[
                        'exercise_id', 'exercise_effort_type', 'exercise_difficulty_unit',
                        'sets' => [['order', 'effort_value', 'difficulty_value']],
                    ]],
                ],
            ]);
    }

    #[Test]
    public function create_requires_english_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/workout-templates', $this->validPayload([
            'translations' => ['en' => ['name' => '']],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('translations.en.name');
    }

    #[Test]
    public function create_requires_at_least_one_activity(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/workout-templates', $this->validPayload([
            'activities' => [],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('activities');
    }

    #[Test]
    public function create_requires_each_activity_to_have_sets(): void
    {
        $admin = User::factory()->admin()->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/workout-templates', $this->validPayload([
            'activities' => [['exercise_id' => $exercise->id, 'order' => 1, 'sets' => []]],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('activities.0.sets');
    }

    #[Test]
    public function create_rejects_out_of_range_heart_rate_zone(): void
    {
        $admin = User::factory()->admin()->create();
        $equipment = Equipment::factory()->heartRateZone()->create();
        $exercise = Exercise::factory()->create(['equipment_id' => $equipment->id]);

        $this->actingAs($admin)->postJson('/api/v1/admin/workout-templates', $this->validPayload([
            'activities' => [[
                'exercise_id' => $exercise->id,
                'order' => 1,
                'sets' => [['order' => 1, 'effort_value' => 1800, 'difficulty_value' => 7]],
            ]],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('activities.0.sets.0.difficulty_value');
    }

    #[Test]
    public function admin_can_update_template_replacing_activities(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)
            ->postJson('/api/v1/admin/workout-templates', $this->validPayload())
            ->json('data.id');

        $newExercise = Exercise::factory()->create();

        $this->actingAs($admin)->putJson("/api/v1/admin/workout-templates/{$id}", [
            'translations' => ['en' => ['name' => 'Upper Body']],
            'activities' => [[
                'exercise_id' => $newExercise->id,
                'order' => 1,
                'sets' => [['order' => 1, 'effort_value' => 12, 'difficulty_value' => null]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $id, 'locale' => 'en', 'field' => 'name', 'value' => 'Upper Body',
        ]);
        // Old activities replaced: only the new exercise remains, and exactly one set.
        $this->assertDatabaseCount('sets', 1);
        $this->assertDatabaseHas('activities', [
            'workout_id' => $id, 'exercise_id' => $newExercise->id,
        ]);
    }

    #[Test]
    public function admin_can_delete_template_and_its_activities_and_sets(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)
            ->postJson('/api/v1/admin/workout-templates', $this->validPayload())
            ->json('data.id');

        $this->actingAs($admin)->deleteJson("/api/v1/admin/workout-templates/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('workout_templates', ['id' => $id]);
        $this->assertDatabaseMissing('activities', ['workout_id' => $id, 'workout_type' => 'workout_template']);
        $this->assertDatabaseCount('sets', 0);
    }

    #[Test]
    public function cannot_delete_a_template_that_is_used_by_a_program(): void
    {
        $admin = User::factory()->admin()->create();
        $template = WorkoutTemplate::factory()->create();
        $program = \App\Models\Program::factory()->create();
        $program->workoutTemplates()->attach($template->id, ['weekday' => 'Monday']);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/workout-templates/{$template->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('workout_templates', ['id' => $template->id]);
    }
}
