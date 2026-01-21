<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutLogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_complete_another_users_workout_log(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUsersLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('workout.logs.complete', ['workoutLog' => $otherUsersLog->id]));

        $response->assertForbidden();

        $otherUsersLog->refresh();
        $this->assertSame('in_progress', $otherUsersLog->status);
    }

    public function test_user_cannot_view_another_users_workout_log(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUsersLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.show', ['id' => $otherUsersLog->id]));

        $response->assertNotFound();
    }
}
