<?php

namespace Tests\Feature\Api\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutLogDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_workout_log(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson(route('api.workout.logs.destroy', ['workout_log' => $workoutLog->id]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('workout_logs', ['id' => $workoutLog->id]);
    }

    public function test_non_owner_cannot_delete_workout_log(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $owner->id,
            'workout_template_id' => WorkoutTemplate::factory(),
        ]);

        $response = $this->actingAs($other)->deleteJson(route('api.workout.logs.destroy', ['workout_log' => $workoutLog->id]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
        ]);
    }
}
