<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_delete_their_own_activity_from_in_progress_workout(): void
    {
        $user = User::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $activity = Activity::factory()->create([
            'workout_type' => WorkoutLog::class,
            'workout_id' => $workoutLog->id,
        ]);

        Set::factory()
            ->count(3)
            ->state(new Sequence(fn (Sequence $sequence) => ['order' => $sequence->index + 1]))
            ->create([
                'activity_id' => $activity->id,
            ]);

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('sets', 3);

        $response = $this
            ->actingAs($user)
            ->delete(route('activities.destroy', ['activity' => $activity->id]));

        $response->assertRedirect();

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('sets', 0);
    }

    #[Test]
    public function user_cannot_delete_another_users_activity(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserWorkoutLog = WorkoutLog::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $activity = Activity::factory()->create([
            'workout_type' => WorkoutLog::class,
            'workout_id' => $otherUserWorkoutLog->id,
        ]);

        Set::factory()
            ->count(2)
            ->state(new Sequence(fn (Sequence $sequence) => ['order' => $sequence->index + 1]))
            ->create([
                'activity_id' => $activity->id,
            ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('activities.destroy', ['activity' => $activity->id]));

        $response->assertForbidden();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('sets', 2);
    }

    #[Test]
    public function user_cannot_delete_activity_from_completed_workout(): void
    {
        $user = User::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $activity = Activity::factory()->create([
            'workout_type' => WorkoutLog::class,
            'workout_id' => $workoutLog->id,
        ]);

        Set::factory()
            ->count(2)
            ->state(new Sequence(fn (Sequence $sequence) => ['order' => $sequence->index + 1]))
            ->create([
                'activity_id' => $activity->id,
            ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('activities.destroy', ['activity' => $activity->id]));

        $response->assertUnprocessable();
        $response->assertSee('Cannot delete activities from completed workouts');

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('sets', 2);
    }

    #[Test]
    public function user_cannot_delete_nonexistent_activity(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('activities.destroy', ['activity' => 99999]));

        $response->assertNotFound();
    }

    #[Test]
    public function guest_cannot_delete_activity(): void
    {
        $workoutLog = WorkoutLog::factory()->create();

        $activity = Activity::factory()->create([
            'workout_type' => WorkoutLog::class,
            'workout_id' => $workoutLog->id,
        ]);

        $response = $this->delete(route('activities.destroy', ['activity' => $activity->id]));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }
}
