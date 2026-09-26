<?php

namespace Tests\Feature\Admin;

use App\Models\Program;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramAdminTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        $template = WorkoutTemplate::factory()->create();

        return array_merge([
            'translations' => [
                'en' => ['name' => 'Beginner Cycling', 'description' => 'Seven-day plan.'],
                'ru' => ['name' => 'Велозаезд для новичков', 'description' => 'План на неделю.'],
            ],
            'assignments' => [
                ['workout_template_id' => $template->id, 'weekday' => 'Monday'],
            ],
        ], $overrides);
    }

    #[Test]
    public function non_admin_cannot_list_programs(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/programs')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_list_programs_with_template_counts(): void
    {
        $admin = User::factory()->admin()->create();
        Program::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/programs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'templates_count', 'translations' => ['name' => ['en', 'ru']]]],
            ]);
    }

    #[Test]
    public function admin_can_create_program_with_weekday_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $monday = WorkoutTemplate::factory()->create();
        $wednesday = WorkoutTemplate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/programs', $this->validPayload([
            'assignments' => [
                ['workout_template_id' => $monday->id, 'weekday' => 'Monday'],
                ['workout_template_id' => $wednesday->id, 'weekday' => 'Wednesday'],
            ],
        ]));

        $response->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'program', 'translatable_id' => $id,
            'locale' => 'ru', 'field' => 'name', 'value' => 'Велозаезд для новичков',
        ]);
        $this->assertDatabaseHas('program_workout_template', [
            'program_id' => $id, 'workout_template_id' => $monday->id, 'weekday' => 'Monday',
        ]);
        $this->assertDatabaseHas('program_workout_template', [
            'program_id' => $id, 'workout_template_id' => $wednesday->id, 'weekday' => 'Wednesday',
        ]);
    }

    #[Test]
    public function admin_can_assign_the_same_template_to_multiple_weekdays(): void
    {
        $admin = User::factory()->admin()->create();
        $template = WorkoutTemplate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/programs', $this->validPayload([
            'assignments' => [
                ['workout_template_id' => $template->id, 'weekday' => 'Tuesday'],
                ['workout_template_id' => $template->id, 'weekday' => 'Thursday'],
            ],
        ]));

        $response->assertCreated();
        $this->assertDatabaseCount('program_workout_template', 2);
    }

    #[Test]
    public function non_admin_cannot_create_program(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/programs', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('programs', 0);
    }

    #[Test]
    public function program_can_be_created_without_assignments(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/programs', $this->validPayload([
            'assignments' => [],
        ]))->assertCreated();
    }

    #[Test]
    public function create_requires_english_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/programs', $this->validPayload([
            'translations' => ['en' => ['name' => '']],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('translations.en.name');
    }

    #[Test]
    public function create_rejects_invalid_weekday(): void
    {
        $admin = User::factory()->admin()->create();
        $template = WorkoutTemplate::factory()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/programs', $this->validPayload([
            'assignments' => [['workout_template_id' => $template->id, 'weekday' => 'Someday']],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('assignments.0.weekday');
    }

    #[Test]
    public function create_rejects_unknown_template(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/programs', $this->validPayload([
            'assignments' => [['workout_template_id' => 99999, 'weekday' => 'Monday']],
        ]))->assertUnprocessable()->assertJsonValidationErrorFor('assignments.0.workout_template_id');
    }

    #[Test]
    public function admin_can_show_program_with_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)
            ->postJson('/api/v1/admin/programs', $this->validPayload())
            ->json('data.id');

        $this->actingAs($admin)->getJson("/api/v1/admin/programs/{$id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'translations' => ['name' => ['en', 'ru'], 'description' => ['en', 'ru']],
                    'assignments' => [['workout_template_id', 'weekday', 'weekday_label']],
                ],
            ]);
    }

    #[Test]
    public function admin_can_update_program_replacing_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)
            ->postJson('/api/v1/admin/programs', $this->validPayload())
            ->json('data.id');

        $newTemplate = WorkoutTemplate::factory()->create();

        $this->actingAs($admin)->putJson("/api/v1/admin/programs/{$id}", [
            'translations' => ['en' => ['name' => 'Intermediate Cycling']],
            'assignments' => [['workout_template_id' => $newTemplate->id, 'weekday' => 'Friday']],
        ])->assertOk();

        $this->assertDatabaseCount('program_workout_template', 1);
        $this->assertDatabaseHas('program_workout_template', [
            'program_id' => $id, 'workout_template_id' => $newTemplate->id, 'weekday' => 'Friday',
        ]);
    }

    #[Test]
    public function admin_can_delete_program_and_its_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)
            ->postJson('/api/v1/admin/programs', $this->validPayload())
            ->json('data.id');

        $this->actingAs($admin)->deleteJson("/api/v1/admin/programs/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('programs', ['id' => $id]);
        $this->assertDatabaseCount('program_workout_template', 0);
    }
}
