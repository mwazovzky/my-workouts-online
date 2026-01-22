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

    public function test_user_cannot_complete_another_users_workout_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUsersLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => $otherUsersLog->id]));

        $response->assertForbidden();

        $this->assertDatabaseHas('workout_logs', [
            'id' => $otherUsersLog->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_user_cannot_complete_already_completed_workout_log(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => $workoutLog->id]));

        $response->assertForbidden();

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
            'status' => 'completed',
        ]);
    }

    public function test_user_cannot_complete_nonexistent_workout_log(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => 99999]));

        $response->assertNotFound();
    }

    public function test_guest_user_redirected_when_attempting_to_complete_workout(): void
    {
        $workoutLog = WorkoutLog::factory()->create([
            'status' => 'in_progress',
        ]);

        $response = $this->post(route('workout.logs.complete', ['workoutLog' => $workoutLog->id]));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
            'status' => 'in_progress',
        ]);
    }
}
