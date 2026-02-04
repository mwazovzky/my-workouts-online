<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_complete_another_users_workout_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkoutLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('workout.logs.complete', ['workoutLog' => $otherUserWorkoutLog->id]));

        $response->assertForbidden();

        $otherUserWorkoutLog->refresh();
        $this->assertSame('in_progress', $otherUserWorkoutLog->status->value);
    }

    #[Test]
    public function user_cannot_view_another_users_workout_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkoutLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.show', ['id' => $otherUserWorkoutLog->id]));

        $response->assertNotFound();
    }
}
