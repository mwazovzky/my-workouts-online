<?php

namespace Tests\Feature\Api\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutLogCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_workout_changes_status_to_completed(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->postJson(route('api.workout.logs.complete', ['workout_log' => $workoutLog->id]));

        $response->assertOk();

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
            'status' => 'completed',
        ]);
    }
}
