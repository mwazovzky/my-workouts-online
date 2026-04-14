<?php

namespace Tests\Feature\Workout;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutCompleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function complete_workout_changes_status_to_completed(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/complete");

        $response->assertOk();

        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function user_cannot_complete_another_users_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkout = Workout::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/workouts/{$otherUserWorkout->id}/complete");

        $response->assertForbidden();

        $this->assertDatabaseHas('workouts', [
            'id' => $otherUserWorkout->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function user_cannot_complete_already_completed_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/workouts/{$workout->id}/complete");

        $response->assertForbidden();

        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function user_cannot_complete_nonexistent_workout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/workouts/99999/complete');

        $response->assertNotFound();
    }

    #[Test]
    public function guest_user_gets_unauthorized_when_attempting_to_complete_workout(): void
    {
        $workout = Workout::factory()->create([
            'status' => 'in_progress',
        ]);

        $response = $this->postJson("/api/v1/workouts/{$workout->id}/complete");

        $response->assertUnauthorized();

        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'status' => 'in_progress',
        ]);
    }
}
