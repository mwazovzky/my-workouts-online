<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\WorkoutLogResource;
use App\Models\Activity;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_transforms_workout_log_to_correct_json_structure(): void
    {
        $user = User::factory()->create();
        $workoutTemplate = WorkoutTemplate::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $workoutTemplate->id,
            'status' => 'in_progress',
        ]);

        $resource = new WorkoutLogResource($workoutLog);
        $array = $resource->toArray(request());

        $this->assertIsArray($array);
        $this->assertEquals($workoutLog->id, $array['id']);
        $this->assertEquals($user->id, $array['user_id']);
        $this->assertEquals('in_progress', $array['status']);
        $this->assertNotNull($array['created_at']);
    }

    #[Test]
    public function activities_relationship_conditionally_included_when_loaded(): void
    {
        $workoutLog = WorkoutLog::factory()->create();

        Activity::factory()
            ->for($workoutLog, 'workout')
            ->count(3)
            ->sequence(
                ['order' => 1],
                ['order' => 2],
                ['order' => 3]
            )
            ->create();

        // Without eager loading
        $workoutLogWithoutActivities = WorkoutLog::find($workoutLog->id);
        $resourceWithout = new WorkoutLogResource($workoutLogWithoutActivities);
        $arrayWithout = $resourceWithout->toArray(request());

        $this->assertEmpty($arrayWithout['activities']);

        // With eager loading
        $workoutLogWithActivities = WorkoutLog::with('activities')->find($workoutLog->id);
        $resourceWith = new WorkoutLogResource($workoutLogWithActivities);
        $arrayWith = $resourceWith->toArray(request());

        $this->assertCount(3, $arrayWith['activities']);
    }

    #[Test]
    public function activities_count_conditionally_included_when_counted(): void
    {
        $workoutLog = WorkoutLog::factory()->create();

        Activity::factory()
            ->for($workoutLog, 'workout')
            ->count(5)
            ->sequence(
                ['order' => 1],
                ['order' => 2],
                ['order' => 3],
                ['order' => 4],
                ['order' => 5]
            )
            ->create();

        // Without withCount
        $workoutLogWithoutCount = WorkoutLog::find($workoutLog->id);
        $resourceWithout = new WorkoutLogResource($workoutLogWithoutCount);
        $arrayWithout = $resourceWithout->toArray(request());

        $this->assertNull($arrayWithout['activities_count']);

        // With withCount
        $workoutLogWithCount = WorkoutLog::withCount('activities')->find($workoutLog->id);
        $resourceWith = new WorkoutLogResource($workoutLogWithCount);
        $arrayWith = $resourceWith->toArray(request());

        $this->assertEquals(5, $arrayWith['activities_count']);
    }

    #[Test]
    public function workout_template_conditionally_included_when_loaded(): void
    {
        $workoutTemplate = WorkoutTemplate::factory()->create(['name' => 'Test Template']);
        $workoutLog = WorkoutLog::factory()->create([
            'workout_template_id' => $workoutTemplate->id,
        ]);

        // Without eager loading
        $workoutLogWithoutTemplate = WorkoutLog::find($workoutLog->id);
        $resourceWithout = new WorkoutLogResource($workoutLogWithoutTemplate);
        $arrayWithout = $resourceWithout->toArray(request());

        $this->assertNull($arrayWithout['workout_template']);

        // With eager loading
        $workoutLogWithTemplate = WorkoutLog::with('workoutTemplate')->find($workoutLog->id);
        $resourceWith = new WorkoutLogResource($workoutLogWithTemplate);
        $arrayWith = $resourceWith->toArray(request());

        $this->assertNotNull($arrayWith['workout_template']);
        $this->assertEquals('Test Template', $arrayWith['workout_template']['name']);
    }

    #[Test]
    public function resource_includes_all_expected_keys(): void
    {
        $workoutLog = WorkoutLog::factory()->create();
        $resource = new WorkoutLogResource($workoutLog);
        $array = $resource->toArray(request());

        $expectedKeys = ['id', 'user_id', 'status', 'created_at', 'activities_count', 'workout_template', 'activities'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }
}
