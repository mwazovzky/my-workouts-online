<?php

namespace Tests\Unit\Services\Activity;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Services\Activity\ActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_replaces_existing_sets(): void
    {
        $user = User::factory()
            ->create();

        $workoutLog = WorkoutLog::factory()
            ->create(['user_id' => $user->id]);

        $activity = Activity::factory()
            ->for($workoutLog, 'workout')
            ->create();

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 2, 'repetitions' => 5, 'weight' => 50]);

        $service = new ActivityService;

        $newSets = [
            ['order' => 1, 'repetitions' => 8, 'weight' => 55],
            ['order' => 2, 'repetitions' => 6, 'weight' => 60],
            ['order' => 3, 'repetitions' => 4, 'weight' => 65],
        ];

        $updated = $service->update($activity, ['sets' => $newSets]);

        $this->assertCount(3, $updated->sets);

        $this->assertDatabaseMissing('sets', [
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 5,
            'weight' => 50,
        ]);

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
    }
}
