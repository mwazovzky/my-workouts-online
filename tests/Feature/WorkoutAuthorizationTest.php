<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_complete_another_users_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkout = Workout::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('workouts.complete', ['workout' => $otherUserWorkout->id]));

        $response->assertForbidden();

        $otherUserWorkout->refresh();
        $this->assertSame('in_progress', $otherUserWorkout->status->value);
    }

    #[Test]
    public function user_cannot_view_another_users_workout_via_api(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkout = Workout::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/workouts/{$otherUserWorkout->id}");

        $response->assertNotFound();
    }
}
