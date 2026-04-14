<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // GET /api/v1/programs
    // -------------------------------------------------------

    #[Test]
    public function index_returns_all_programs_with_enrollment_status(): void
    {
        $user = User::factory()->create();
        $enrolled = Program::factory()->create();
        $notEnrolled = Program::factory()->create();

        $user->programs()->attach($enrolled->id);

        $response = $this->actingAs($user)->getJson('/api/v1/programs');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $data = collect($response->json('data'));
        $this->assertTrue($data->firstWhere('id', $enrolled->id)['is_enrolled']);
        $this->assertFalse($data->firstWhere('id', $notEnrolled->id)['is_enrolled']);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/programs')->assertUnauthorized();
    }

    // -------------------------------------------------------
    // GET /api/v1/programs/{id}
    // -------------------------------------------------------

    #[Test]
    public function show_returns_program_with_workout_templates(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/v1/programs/{$program->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $program->id);
        $response->assertJsonStructure(['data' => ['id', 'is_enrolled', 'workout_templates']]);
    }

    #[Test]
    public function show_returns_404_for_nonexistent_program(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/programs/99999')->assertNotFound();
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $program = Program::factory()->create();
        $this->getJson("/api/v1/programs/{$program->id}")->assertUnauthorized();
    }

    // -------------------------------------------------------
    // POST /api/v1/programs/{program}/enroll
    // -------------------------------------------------------

    #[Test]
    public function enroll_adds_user_to_program(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/programs/{$program->id}/enroll");

        $response->assertNoContent();
        $this->assertDatabaseHas('program_user', ['user_id' => $user->id, 'program_id' => $program->id]);
    }

    #[Test]
    public function enroll_is_idempotent(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $user->programs()->attach($program->id);

        $this->actingAs($user)->postJson("/api/v1/programs/{$program->id}/enroll")->assertNoContent();

        $this->assertDatabaseCount('program_user', 1);
    }

    #[Test]
    public function enroll_requires_authentication(): void
    {
        $program = Program::factory()->create();
        $this->postJson("/api/v1/programs/{$program->id}/enroll")->assertUnauthorized();
    }
}
