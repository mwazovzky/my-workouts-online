<?php

namespace Tests\Feature\Program;

use App\Models\Program;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramEnrollTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_enroll_in_a_program(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/programs/{$program->id}/enrollment");

        $response->assertNoContent();

        $this->assertDatabaseHas('program_user', [
            'user_id' => $user->id,
            'program_id' => $program->id,
        ]);
    }

    #[Test]
    public function user_cannot_enroll_in_a_program_twice(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $user->programs()->attach($program);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/programs/{$program->id}/enrollment");

        $response->assertNoContent();
    }

    #[Test]
    public function unauthenticated_user_cannot_enroll_in_a_program(): void
    {
        $program = Program::factory()->create();

        $response = $this->putJson("/api/v1/programs/{$program->id}/enrollment");

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('program_user', [
            'program_id' => $program->id,
        ]);
    }

    #[Test]
    public function cannot_enroll_in_a_nonexistent_program(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/programs/999/enrollment');

        $response->assertNotFound();
    }

    #[Test]
    public function enrolling_twice_leaves_one_enrollment(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $this->actingAs($user)->putJson("/api/v1/programs/{$program->id}/enrollment")->assertNoContent();
        $this->putJson("/api/v1/programs/{$program->id}/enrollment")->assertNoContent();

        $this->assertDatabaseCount('program_user', 1);
    }

    #[Test]
    public function unenroll_removes_the_enrollment_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $user->programs()->attach($program);

        $this->actingAs($user)->deleteJson("/api/v1/programs/{$program->id}/enrollment")->assertNoContent();
        $this->deleteJson("/api/v1/programs/{$program->id}/enrollment")->assertNoContent();

        $this->assertDatabaseMissing('program_user', ['user_id' => $user->id, 'program_id' => $program->id]);
        $this->getJson('/api/v1/programs')->assertOk()->assertJsonPath('data.0.is_enrolled', false);
    }

    #[Test]
    public function unenroll_only_removes_the_callers_enrollment_and_keeps_their_workouts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $program = Program::factory()->create();
        $template = WorkoutTemplate::factory()->create();
        $program->workoutTemplates()->attach($template->id, ['weekday' => 'monday']);
        $program->users()->attach([$user->id, $other->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'workout_template_id' => $template->id]);

        $this->actingAs($user)->deleteJson("/api/v1/programs/{$program->id}/enrollment")->assertNoContent();

        $this->assertDatabaseHas('program_user', ['user_id' => $other->id, 'program_id' => $program->id]);
        $this->assertModelExists($workout);
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.enrolled_programs_count', 0)
            ->assertJsonCount(0, 'data.upcoming_schedule');
    }

    #[Test]
    public function unenroll_from_a_nonexistent_program_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->assertProblem($this->actingAs($user)->deleteJson('/api/v1/programs/999/enrollment'), 404, 'not_found');
    }

    #[Test]
    public function the_database_refuses_a_duplicate_enrollment(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $user->programs()->attach($program);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('program_user')->insert(['user_id' => $user->id, 'program_id' => $program->id]);
    }

    #[Test]
    public function the_old_enroll_action_is_gone(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $this->assertProblem($this->actingAs($user)->postJson("/api/v1/programs/{$program->id}/enroll"), 404, 'not_found');
    }
}
