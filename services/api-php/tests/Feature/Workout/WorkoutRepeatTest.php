<?php

namespace Tests\Feature\Workout;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutRepeatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_repeat_a_completed_workout_and_it_copies_activities_and_sets_and_resets_completion(): void
    {
        $user = User::factory()->create();

        $sourceWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'workout_template_id' => null,
            'name' => 'Leg Day',
        ]);

        $activity = Activity::factory()->for($sourceWorkout, 'workout')->create(['order' => 1]);

        Set::factory()
            ->for($activity, 'activity')
            ->count(2)
            ->state(new Sequence(
                ['order' => 1, 'effort_value' => 10, 'difficulty_value' => 100, 'is_completed' => true],
                ['order' => 2, 'effort_value' => 8, 'difficulty_value' => 110, 'is_completed' => false],
            ))
            ->create();

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/workouts/{$sourceWorkout->id}/repeat");

        $response->assertCreated();

        $newWorkout = Workout::query()->latest('id')->first();

        $this->assertNotNull($newWorkout);
        $this->assertNotSame($sourceWorkout->id, $newWorkout->id);

        $this->assertDatabaseHas('workouts', [
            'id' => $newWorkout->id,
            'user_id' => $user->id,
            'workout_template_id' => null,
            'name' => 'Leg Day',
            'status' => 'in_progress',
        ]);

        $newActivity = $newWorkout->activities()->first();
        $this->assertNotNull($newActivity);

        $this->assertDatabaseHas('activities', [
            'id' => $newActivity->id,
            'workout_id' => $newWorkout->id,
            'workout_type' => 'workout',
            'exercise_id' => $activity->exercise_id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $newActivity->id,
            'order' => 1,
            'effort_value' => 10,
            'difficulty_value' => 100,
            'is_completed' => 0,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $newActivity->id,
            'order' => 2,
            'effort_value' => 8,
            'difficulty_value' => 110,
            'is_completed' => 0,
        ]);
    }

    #[Test]
    public function non_owner_cannot_repeat_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $sourceWorkout = Workout::factory()->create([
            'user_id' => $owner->id,
            'status' => 'completed',
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->postJson("/api/v1/workouts/{$sourceWorkout->id}/repeat");

        $response->assertForbidden();
    }

    #[Test]
    public function owner_cannot_repeat_an_in_progress_workout(): void
    {
        $user = User::factory()->create();

        $sourceWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/workouts/{$sourceWorkout->id}/repeat");

        $response->assertForbidden();
    }
}
