<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramControllerTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_can_fetch_assigned_programs(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $program->users()->attach($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/programs')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'id' => $program->id,
                        'name' => $program->name,
                        'description' => $program->description,
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_it_returns_program_details(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $workoutTemplates = \App\Models\WorkoutTemplate::factory()->count(3)->create();

        foreach ($workoutTemplates as $index => $workoutTemplate) {
            $program->workoutTemplates()->attach($workoutTemplate, ['weekday' => ['Monday', 'Wednesday', 'Friday'][$index]]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.programs.show', $program->id));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'workouts' => $program->workoutTemplates->map(function ($workout) {
                    return [
                        'id' => $workout->id,
                        'name' => $workout->name,
                        'weekday' => $workout->pivot->weekday,
                    ];
                })->toArray(),
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_it_returns_404_if_program_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson(route('api.programs.show', 999));

        $response->assertNotFound();
        $response->assertJson(['message' => 'Program not found']);
    }
}
