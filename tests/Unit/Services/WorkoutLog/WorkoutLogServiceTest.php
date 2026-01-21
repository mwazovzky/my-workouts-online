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
use Tests\TestCase;

class WorkoutLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_template_creates_workout_log_and_copies_activities_and_sets(): void
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

    public function test_create_from_template_throws_when_template_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $user = User::factory()->create();

        $service = new WorkoutLogService;
        $service->createFromTemplate($user, 999);
    }
}
