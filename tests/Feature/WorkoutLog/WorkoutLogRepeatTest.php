<?php

namespace Tests\Feature\WorkoutLog;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogRepeatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_repeat_a_completed_workout_log_and_it_copies_activities_and_sets_and_resets_completion(): void
    {
        $user = User::factory()->create();

        $sourceLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'workout_template_id' => null,
            'name' => 'Leg Day',
        ]);

        $activity = Activity::factory()->for($sourceLog, 'workout')->create(['order' => 1]);

        Set::factory()
            ->for($activity, 'activity')
            ->count(2)
            ->state(new Sequence(
                ['order' => 1, 'repetitions' => 10, 'weight' => 100, 'is_completed' => true],
                ['order' => 2, 'repetitions' => 8, 'weight' => 110, 'is_completed' => false],
            ))
            ->create();

        $response = $this
            ->actingAs($user)
            ->post(route('workout.logs.repeat', ['workoutLog' => $sourceLog->id]));

        $response->assertRedirect();

        $newLog = WorkoutLog::query()->latest('id')->first();

        $this->assertNotNull($newLog);
        $this->assertNotSame($sourceLog->id, $newLog->id);

        $this->assertDatabaseHas('workout_logs', [
            'id' => $newLog->id,
            'user_id' => $user->id,
            'workout_template_id' => null,
            'name' => 'Leg Day',
            'status' => 'in_progress',
        ]);

        $newActivity = $newLog->activities()->first();
        $this->assertNotNull($newActivity);

        $this->assertDatabaseHas('activities', [
            'id' => $newActivity->id,
            'workout_id' => $newLog->id,
            'workout_type' => 'workout_log',
            'exercise_id' => $activity->exercise_id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $newActivity->id,
            'order' => 1,
            'repetitions' => 10,
            'weight' => 100,
            'is_completed' => 0,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $newActivity->id,
            'order' => 2,
            'repetitions' => 8,
            'weight' => 110,
            'is_completed' => 0,
        ]);
    }

    #[Test]
    public function non_owner_cannot_repeat_another_users_workout_log(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $sourceLog = WorkoutLog::factory()->create([
            'user_id' => $owner->id,
            'status' => 'completed',
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->post(route('workout.logs.repeat', ['workoutLog' => $sourceLog->id]));

        $response->assertForbidden();
    }

    #[Test]
    public function owner_cannot_repeat_an_in_progress_workout_log(): void
    {
        $user = User::factory()->create();

        $sourceLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('workout.logs.repeat', ['workoutLog' => $sourceLog->id]));

        $response->assertForbidden();
    }
}
