<?php

namespace Tests\Feature\Api\Activitity;

use App\Models\Activity;
use App\Models\Exercise;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_activities_replaces_sets_for_existing_activity(): void
    {
        $user = User::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $exercise = Exercise::factory()->create([
            'name' => 'Bench Press',
        ]);

        $activity = Activity::factory()
            ->for($workoutLog, 'workout')
            ->for($exercise)
            ->create();

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 2, 'repetitions' => 5, 'weight' => 50]);

        $payload = [
            'sets' => [
                ['order' => 1, 'repetitions' => 8, 'weight' => 55],
                ['order' => 2, 'repetitions' => 6, 'weight' => 60],
                ['order' => 3, 'repetitions' => 4, 'weight' => 65],
            ],
        ];

        $response = $this->actingAs($user)->patchJson(
            route('api.activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertOk();

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'exercise_id' => $exercise->id,
        ]);
        // existing sets are removed
        $this->assertDatabaseMissing('sets', [
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 5,
            'weight' => 50,
        ]);
        $this->assertDatabaseMissing('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'repetitions' => 5,
            'weight' => 50,
        ]);
        // new sets are created
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 8,
            'weight' => 55,
        ]);
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'repetitions' => 6,
            'weight' => 60,
        ]);
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 3,
            'repetitions' => 4,
            'weight' => 65,
        ]);
        // response payload should include activity id and sets
        $response->assertJsonFragment(['id' => $activity->id]);
        $response->assertJsonStructure(['data' => ['id', 'sets']]);
    }
}
