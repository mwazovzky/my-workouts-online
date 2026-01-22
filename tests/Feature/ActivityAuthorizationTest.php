<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_update_another_users_activity(): void
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

        $set = Set::factory()->create([
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 5,
            'weight' => 10,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => $activity->id]), [
                'sets' => [
                    ['order' => 1, 'repetitions' => 99, 'weight' => 999],
                ],
            ]);

        $response->assertForbidden();

        $set->refresh();
        $this->assertSame(5, $set->repetitions);
        $this->assertSame(10.0, (float) $set->weight);
    }

    #[Test]
    public function user_cannot_update_activity_in_completed_workout_log(): void
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

        $set = Set::factory()->create([
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 5,
            'weight' => 10,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => $activity->id]), [
                'sets' => [
                    ['order' => 1, 'repetitions' => 99, 'weight' => 999],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertSee('Cannot update activities in completed workouts');

        $set->refresh();
        $this->assertSame(5, $set->repetitions);
        $this->assertSame(10.0, (float) $set->weight);
    }

    #[Test]
    public function user_cannot_update_nonexistent_activity(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => 99999]), [
                'sets' => [
                    ['order' => 1, 'repetitions' => 10, 'weight' => 50],
                ],
            ]);

        $response->assertNotFound();
    }

    #[Test]
    public function guest_user_redirected_when_attempting_to_update_activity(): void
    {
        $workoutLog = WorkoutLog::factory()->create();

        $activity = Activity::factory()->create([
            'workout_type' => WorkoutLog::class,
            'workout_id' => $workoutLog->id,
        ]);

        $set = Set::factory()->create([
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 5,
            'weight' => 10,
        ]);

        $response = $this->patch(route('activities.update', ['activity' => $activity->id]), [
            'sets' => [
                ['order' => 1, 'repetitions' => 99, 'weight' => 999],
            ],
        ]);

        $response->assertRedirect(route('login'));

        $set->refresh();
        $this->assertSame(5, $set->repetitions);
        $this->assertSame(10.0, (float) $set->weight);
    }
}
