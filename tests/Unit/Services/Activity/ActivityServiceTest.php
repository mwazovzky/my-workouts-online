<?php

namespace Tests\Unit\Services\Activity;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Services\Activity\ActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_diffs_sets_by_id_update_create_delete(): void
    {
        $user = User::factory()
            ->create();

        $workoutLog = WorkoutLog::factory()
            ->create(['user_id' => $user->id]);

        $activity = Activity::factory()
            ->for($workoutLog, 'workout')
            ->create();

        $set1 = Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);

        $set2 = Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 2, 'repetitions' => 5, 'weight' => 50]);

        $service = new ActivityService;

        $newSets = [
            ['id' => $set1->id, 'order' => 1, 'repetitions' => 8, 'weight' => 55],
            ['order' => 3, 'repetitions' => 4, 'weight' => 65],
        ];

        $updated = $service->update($activity, ['sets' => $newSets]);

        $this->assertCount(2, $updated->sets);

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

    #[Test]
    public function update_with_empty_sets_array_deletes_all_sets(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        Set::factory()->for($activity, 'activity')->create(['order' => 1]);
        Set::factory()->for($activity, 'activity')->create(['order' => 2]);

        $service = new ActivityService;
        $updated = $service->update($activity, ['sets' => []]);

        $this->assertCount(0, $updated->sets);
        $this->assertDatabaseCount('sets', 0);
    }

    #[Test]
    public function update_preserves_set_attributes_not_in_payload(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        $activity = Activity::factory()->for($workoutLog, 'workout')->create();

        $set = Set::factory()->for($activity, 'activity')->create([
            'order' => 1,
            'repetitions' => 10,
            'weight' => 100,
        ]);

        $originalCreatedAt = $set->created_at;
        $originalActivityId = $set->activity_id;

        // Update only repetitions and weight, order should be recalculated
        $service = new ActivityService;
        $updated = $service->update($activity, [
            'sets' => [
                ['id' => $set->id, 'order' => 1, 'repetitions' => 12, 'weight' => 110],
            ],
        ]);

        $set->refresh();

        $this->assertEquals(12, $set->repetitions);
        $this->assertEquals(110, $set->weight);
        $this->assertEquals($originalActivityId, $set->activity_id);
        $this->assertEquals($originalCreatedAt->timestamp, $set->created_at->timestamp);
    }

    #[Test]
    public function update_throws_exception_when_set_id_belongs_to_different_activity(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);

        $activity1 = Activity::factory()->for($workoutLog, 'workout')->create(['order' => 1]);
        $activity2 = Activity::factory()->for($workoutLog, 'workout')->create(['order' => 2]);

        $set1 = Set::factory()->for($activity1, 'activity')->create(['order' => 1]);
        $set2 = Set::factory()->for($activity2, 'activity')->create(['order' => 1]);

        $service = new ActivityService;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('One or more sets do not belong to this activity');

        // Try to update activity1 with a set that belongs to activity2
        $service->update($activity1, [
            'sets' => [
                ['id' => $set2->id, 'order' => 1, 'repetitions' => 5, 'weight' => 50],
            ],
        ]);
    }
}
