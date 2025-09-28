<?php

namespace Tests\Feature\Api\Program;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramEnrollTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_enroll_in_a_program()
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/programs/{$program->id}/enroll");

        $response->assertStatus(204);
        $response->assertNoContent();
        $this->assertDatabaseHas('program_user', [
            'user_id' => $user->id,
            'program_id' => $program->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_cannot_enroll_in_a_program_twice()
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $user->programs()->attach($program);

        $response = $this->actingAs($user)->postJson("/api/programs/{$program->id}/enroll");

        $response->assertStatus(204);
        $response->assertNoContent();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_enroll_in_a_program()
    {
        $program = Program::factory()->create();

        $response = $this->postJson("/api/programs/{$program->id}/enroll");

        $response->assertStatus(401);
        $this->assertDatabaseMissing('program_user', [
            'program_id' => $program->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_enroll_in_a_nonexistent_program()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/programs/999/enroll");

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Record not found.',
        ]);
    }
}
