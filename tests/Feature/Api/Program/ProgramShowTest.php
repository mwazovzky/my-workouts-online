<?php

namespace Tests\Feature\Api\Program;

use App\Models\Program;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-01-01 10:00:00');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_it_returns_program_details_with_workout_templates(): void
    {
        $user = User::factory()->create();
        $program = Program::factory()->create();
        $workoutTemplates = WorkoutTemplate::factory()->count(3)->create();

        foreach ($workoutTemplates as $index => $workoutTemplate) {
            $program->workoutTemplates()->attach($workoutTemplate, ['weekday' => ['Monday', 'Wednesday', 'Friday'][$index]]);
        }

        $workoutTemplatesWithPivot = $program->workoutTemplates()->withPivot('weekday')->get();

        $response = $this->actingAs($user)->getJson(route('api.programs.show', $program->id));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $program->id,
            'name' => $program->name,
            'workouts' => [
                [
                    'id' => $workoutTemplatesWithPivot[0]->id,
                    'name' => $workoutTemplatesWithPivot[0]->name,
                    'description' => $workoutTemplatesWithPivot[0]->description,
                    'weekday' => $workoutTemplatesWithPivot[0]->pivot->weekday,
                    'activities_count' => 0,
                    'created_at' => now()->toJSON(),
                    'updated_at' => now()->toJSON(),
                ],
                [
                    'id' => $workoutTemplatesWithPivot[1]->id,
                    'name' => $workoutTemplatesWithPivot[1]->name,
                    'description' => $workoutTemplatesWithPivot[1]->description,
                    'weekday' => $workoutTemplatesWithPivot[1]->pivot->weekday,
                    'activities_count' => 0,
                    'created_at' => now()->toJSON(),
                    'updated_at' => now()->toJSON(),
                ],
                [
                    'id' => $workoutTemplatesWithPivot[2]->id,
                    'name' => $workoutTemplatesWithPivot[2]->name,
                    'description' => $workoutTemplatesWithPivot[2]->description,
                    'weekday' => $workoutTemplatesWithPivot[2]->pivot->weekday,
                    'activities_count' => 0,
                    'created_at' => now()->toJSON(),
                    'updated_at' => now()->toJSON(),
                ],
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_it_returns_404_if_program_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('api.programs.show', 999));

        $response->assertNotFound();
        $response->assertJson(['message' => 'Record not found.']);
    }
}
