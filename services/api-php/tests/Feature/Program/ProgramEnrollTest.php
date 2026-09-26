<?php

namespace Tests\Feature\Program;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->postJson("/api/v1/programs/{$program->id}/enroll");

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
            ->postJson("/api/v1/programs/{$program->id}/enroll");

        $response->assertNoContent();
    }

    #[Test]
    public function unauthenticated_user_cannot_enroll_in_a_program(): void
    {
        $program = Program::factory()->create();

        $response = $this->postJson("/api/v1/programs/{$program->id}/enroll");

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('program_user', [
            'program_id' => $program->id,
        ]);
    }

    #[Test]
    public function cannot_enroll_in_a_nonexistent_program(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/programs/999/enroll');

        $response->assertNotFound();
    }
}
