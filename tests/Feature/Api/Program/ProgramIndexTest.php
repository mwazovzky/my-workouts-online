<?php

namespace Tests\Feature\Api\Program;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramIndexTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_can_fetch_assigned_programs(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $program->users()->attach($user);

        $response = $this->actingAs($user)->getJson(route('api.programs.index'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                [
                    'id' => $program->id,
                    'name' => $program->name,
                    'description' => $program->description,
                    'is_enrolled' => true,
                ],
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_can_fetch_unassigned_programs(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();

        $response = $this->actingAs($user)->getJson(route('api.programs.index'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                [
                    'id' => $program->id,
                    'name' => $program->name,
                    'description' => $program->description,
                    'is_enrolled' => false,
                ],
            ],
        ]);
    }
}
