<?php

namespace Tests\Unit\Services\WorkoutLog;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use App\Services\WorkoutLog\WorkoutLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_from_template_creates_workout_log_and_copies_activities_and_sets(): void
    {
        $user = User::factory()->create();

        $workoutTemplate = WorkoutTemplate::factory()->create();

        $workoutTemplateActivity = Activity::factory()
            ->for($workoutTemplate, 'workout')
            ->create(['order' => 1]);

        $setOne = Set::factory()
            ->for($workoutTemplateActivity, 'activity')
            ->create(['order' => 1, 'repetitions' => 10, 'weight' => 20]);

        $setTwo = Set::factory()
            ->for($workoutTemplateActivity, 'activity')
            ->create(['order' => 2, 'repetitions' => 8, 'weight' => 22]);

        $service = new WorkoutLogService;
        $workoutLog = $service->createFromTemplate($user, $workoutTemplate->id);

        $this->assertInstanceOf(WorkoutLog::class, $workoutLog);
        $this->assertEquals($workoutTemplate->id, $workoutLog->workout_template_id);
        $this->assertEquals('in_progress', $workoutLog->status);

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
            'workout_template_id' => $workoutTemplate->id,
            'user_id' => $user->id,
        ]);

        $workoutActivity = Activity::where('workout_id', $workoutLog->id)->first();
        $this->assertNotNull($workoutActivity);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $workoutActivity->id,
            'order' => $setOne->order,
            'repetitions' => $setOne->repetitions,
            'weight' => $setOne->weight,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $workoutActivity->id,
            'order' => $setTwo->order,
            'repetitions' => $setTwo->repetitions,
            'weight' => $setTwo->weight,
        ]);
    }

    #[Test]
    public function create_from_template_throws_when_template_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $user = User::factory()->create();

        $service = new WorkoutLogService;
        $service->createFromTemplate($user, 999);
    }

    #[Test]
    public function delete_cascades_to_activities_and_sets(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create(['user_id' => $user->id]);

        $activity = Activity::factory()
            ->for($workoutLog, 'workout')
            ->create(['order' => 1]);

        $set = Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1]);

        $activityId = $activity->id;
        $setId = $set->id;
        $workoutLogId = $workoutLog->id;

        $service = new WorkoutLogService;
        $service->delete($workoutLog);

        // Verify workout log is deleted
        $this->assertDatabaseMissing('workout_logs', ['id' => $workoutLogId]);

        // Verify activities are deleted via service
        $this->assertDatabaseMissing('activities', ['id' => $activityId]);

        // Verify sets are deleted via database cascade
        $this->assertDatabaseMissing('sets', ['id' => $setId]);
    }
}
