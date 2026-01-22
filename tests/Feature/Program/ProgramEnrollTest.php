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

        $response = $this->actingAs($user)->post(route('programs.enroll', ['program' => $program->id]));

        $response->assertRedirect(route('programs.show', ['id' => $program->id]));

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

        $response = $this->actingAs($user)->post(route('programs.enroll', ['program' => $program->id]));

        $response->assertRedirect(route('programs.show', ['id' => $program->id]));
    }

    #[Test]
    public function unauthenticated_user_cannot_enroll_in_a_program(): void
    {
        $program = Program::factory()->create();

        $response = $this->post(route('programs.enroll', ['program' => $program->id]));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseMissing('program_user', [
            'program_id' => $program->id,
        ]);
    }

    #[Test]
    public function cannot_enroll_in_a_nonexistent_program(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('programs.enroll', ['program' => 999]));

        $response->assertNotFound();
    }
}
