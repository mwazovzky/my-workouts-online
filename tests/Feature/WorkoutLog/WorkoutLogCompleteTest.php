<?php

namespace Tests\Feature\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogCompleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function complete_workout_changes_status_to_completed(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => $workoutLog->id]));

        $response->assertRedirect(route('workout.logs.show', ['id' => $workoutLog->id]));

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function user_cannot_complete_another_users_workout_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkoutLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => $otherUserWorkoutLog->id]));

        $response->assertForbidden();

        $this->assertDatabaseHas('workout_logs', [
            'id' => $otherUserWorkoutLog->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function user_cannot_complete_already_completed_workout_log(): void
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

    #[Test]
    public function user_cannot_complete_nonexistent_workout_log(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workout.logs.complete', ['workoutLog' => 99999]));

        $response->assertNotFound();
    }

    #[Test]
    public function guest_user_redirected_when_attempting_to_complete_workout(): void
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
