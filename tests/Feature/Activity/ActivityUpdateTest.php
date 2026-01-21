<?php

namespace Tests\Feature\Activity;

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

    public function test_update_activity_diffs_sets_by_id_update_create_delete(): void
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

        $set1 = Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);

        $set2 = Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 2, 'repetitions' => 5, 'weight' => 50]);

        $payload = [
            'sets' => [
                ['id' => $set1->id, 'order' => 1, 'repetitions' => 8, 'weight' => 55],
                ['order' => 2, 'repetitions' => 4, 'weight' => 65],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertDatabaseMissing('sets', [
            'id' => $set2->id,
        ]);

        $this->assertDatabaseHas('sets', [
            'id' => $set1->id,
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 8,
            'weight' => 55,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'repetitions' => 4,
            'weight' => 65,
        ]);
    }

    public function test_delete_first_set_and_orders_are_normalized(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);

        // Frontend sends [2, 3] after deleting first set
        $payload = [
            'sets' => [
                ['id' => $set2->id, 'order' => 2, 'repetitions' => 6, 'weight' => 60],
                ['id' => $set3->id, 'order' => 3, 'repetitions' => 7, 'weight' => 70],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        // Set1 should be deleted
        $this->assertDatabaseMissing('sets', ['id' => $set1->id]);

        // Remaining sets should be normalized to [1, 2]
        $this->assertDatabaseHas('sets', [
            'id' => $set2->id,
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 6,
            'weight' => 60,
        ]);

        $this->assertDatabaseHas('sets', [
            'id' => $set3->id,
            'activity_id' => $activity->id,
            'order' => 2,
            'repetitions' => 7,
            'weight' => 70,
        ]);
    }

    public function test_delete_middle_set_and_orders_are_normalized(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);

        // Frontend sends [1, 3] after deleting middle set
        $payload = [
            'sets' => [
                ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                ['id' => $set3->id, 'order' => 3, 'repetitions' => 7, 'weight' => 70],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        // Set2 should be deleted
        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);

        // Remaining sets should be normalized to [1, 2]
        $this->assertDatabaseHas('sets', [
            'id' => $set1->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('sets', [
            'id' => $set3->id,
            'order' => 2,
        ]);
    }

    public function test_delete_last_set_and_orders_remain_correct(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);

        // Frontend sends [1, 2] after deleting last set
        $payload = [
            'sets' => [
                ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                ['id' => $set2->id, 'order' => 2, 'repetitions' => 6, 'weight' => 60],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        // Set3 should be deleted
        $this->assertDatabaseMissing('sets', ['id' => $set3->id]);

        // Remaining sets should stay [1, 2]
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1]);
        $this->assertDatabaseHas('sets', ['id' => $set2->id, 'order' => 2]);
    }

    public function test_add_new_set_with_non_sequential_order_is_normalized(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);

        // Frontend sends [1, 2, 99] - new set has order 99 (max + 1 logic after some deletes)
        $payload = [
            'sets' => [
                ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                ['id' => $set2->id, 'order' => 2, 'repetitions' => 6, 'weight' => 60],
                ['order' => 99, 'repetitions' => 8, 'weight' => 80],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        // New set should be normalized to order 3
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 3,
            'repetitions' => 8,
            'weight' => 80,
        ]);

        $this->assertEquals(3, Set::where('activity_id', $activity->id)->count());
    }

    public function test_delete_middle_then_add_new_set_normalizes_orders(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);

        // Frontend: delete set2 [1,3], then add new [1,3,4]
        $payload = [
            'sets' => [
                ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                ['id' => $set3->id, 'order' => 3, 'repetitions' => 7, 'weight' => 70],
                ['order' => 4, 'repetitions' => 9, 'weight' => 90],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        // Set2 should be deleted
        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);

        // Remaining sets normalized to [1, 2, 3]
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1]);
        $this->assertDatabaseHas('sets', ['id' => $set3->id, 'order' => 2]);
        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 3,
            'repetitions' => 9,
            'weight' => 90,
        ]);
    }

    public function test_complex_scenario_multiple_deletes_and_adds(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set1 = Set::factory()->for($activity, 'activity')->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);
        $set2 = Set::factory()->for($activity, 'activity')->create(['order' => 2, 'repetitions' => 6, 'weight' => 60]);
        $set3 = Set::factory()->for($activity, 'activity')->create(['order' => 3, 'repetitions' => 7, 'weight' => 70]);
        $set4 = Set::factory()->for($activity, 'activity')->create(['order' => 4, 'repetitions' => 8, 'weight' => 80]);
        $set5 = Set::factory()->for($activity, 'activity')->create(['order' => 5, 'repetitions' => 9, 'weight' => 90]);

        // Delete set2 and set4, keep [1,3,5], add two new sets [1,3,5,6,7]
        $payload = [
            'sets' => [
                ['id' => $set1->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
                ['id' => $set3->id, 'order' => 3, 'repetitions' => 7, 'weight' => 70],
                ['id' => $set5->id, 'order' => 5, 'repetitions' => 9, 'weight' => 90],
                ['order' => 6, 'repetitions' => 10, 'weight' => 100],
                ['order' => 7, 'repetitions' => 11, 'weight' => 110],
            ],
        ];

        $response = $this->actingAs($user)->patch(
            route('activities.update', ['activity' => $activity->id]),
            $payload,
        );

        $response->assertRedirect();

        // Deleted sets
        $this->assertDatabaseMissing('sets', ['id' => $set2->id]);
        $this->assertDatabaseMissing('sets', ['id' => $set4->id]);

        // Normalized to [1, 2, 3, 4, 5]
        $this->assertDatabaseHas('sets', ['id' => $set1->id, 'order' => 1]);
        $this->assertDatabaseHas('sets', ['id' => $set3->id, 'order' => 2]);
        $this->assertDatabaseHas('sets', ['id' => $set5->id, 'order' => 3]);
        $this->assertDatabaseHas('sets', ['activity_id' => $activity->id, 'order' => 4, 'repetitions' => 10]);
        $this->assertDatabaseHas('sets', ['activity_id' => $activity->id, 'order' => 5, 'repetitions' => 11]);

        $this->assertEquals(5, Set::where('activity_id', $activity->id)->count());
    }
}
