<?php

namespace Tests\Feature\WorkoutLog;

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
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => $workoutLog->id]));

        $response->assertRedirect(route('workout.logs.edit', ['id' => $workoutLog->id]));

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
            'status' => 'completed',
        ]);
    }
}
