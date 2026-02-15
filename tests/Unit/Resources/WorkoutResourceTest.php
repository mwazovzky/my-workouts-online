<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\WorkoutResource;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_transforms_workout_to_correct_json_structure(): void
    {
        $user = User::factory()->create();
        $workoutTemplate = WorkoutTemplate::factory()->create();

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $workoutTemplate->id,
            'status' => 'in_progress',
        ]);

        $resource = new WorkoutResource($workout);
        $array = $resource->toArray(request());

        $this->assertIsArray($array);
        $this->assertEquals($workout->id, $array['id']);
        $this->assertEquals($user->id, $array['user_id']);
        $this->assertEquals('in_progress', $array['status']);
        $this->assertNotNull($array['created_at']);
    }

    #[Test]
    public function activities_relationship_conditionally_included_when_loaded(): void
    {
        $workout = Workout::factory()->create();

        Activity::factory()
            ->for($workout, 'workout')
            ->count(3)
            ->sequence(
                ['order' => 1],
                ['order' => 2],
                ['order' => 3]
            )
            ->create();

        // Without eager loading
        $workoutWithoutActivities = Workout::find($workout->id);
        $resourceWithout = new WorkoutResource($workoutWithoutActivities);
        $arrayWithout = $resourceWithout->toArray(request());

        $this->assertEmpty($arrayWithout['activities']);

        // With eager loading
        $workoutWithActivities = Workout::with('activities')->find($workout->id);
        $resourceWith = new WorkoutResource($workoutWithActivities);
        $arrayWith = $resourceWith->toArray(request());

        $this->assertCount(3, $arrayWith['activities']);
    }

    #[Test]
    public function activities_count_conditionally_included_when_counted(): void
    {
        $workout = Workout::factory()->create();

        Activity::factory()
            ->for($workout, 'workout')
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
        $workoutWithoutCount = Workout::find($workout->id);
        $resourceWithout = new WorkoutResource($workoutWithoutCount);
        $arrayWithout = $resourceWithout->toArray(request());

        $this->assertNull($arrayWithout['activities_count']);

        // With withCount
        $workoutWithCount = Workout::withCount('activities')->find($workout->id);
        $resourceWith = new WorkoutResource($workoutWithCount);
        $arrayWith = $resourceWith->toArray(request());

        $this->assertEquals(5, $arrayWith['activities_count']);
    }

    #[Test]
    public function workout_template_conditionally_included_when_loaded(): void
    {
        $workoutTemplate = WorkoutTemplate::factory()->create(['name' => 'Test Template']);
        $workout = Workout::factory()->create([
            'workout_template_id' => $workoutTemplate->id,
        ]);

        // Without eager loading
        $workoutWithoutTemplate = Workout::find($workout->id);
        $resourceWithout = new WorkoutResource($workoutWithoutTemplate);
        $arrayWithout = $resourceWithout->toArray(request());

        $this->assertNull($arrayWithout['workout_template']);

        // With eager loading
        $workoutWithTemplate = Workout::with('workoutTemplate')->find($workout->id);
        $resourceWith = new WorkoutResource($workoutWithTemplate);
        $arrayWith = $resourceWith->toArray(request());

        $this->assertNotNull($arrayWith['workout_template']);
        $this->assertEquals('Test Template', $arrayWith['workout_template']['name']);
    }

    #[Test]
    public function resource_includes_all_expected_keys(): void
    {
        $workout = Workout::factory()->create();
        $resource = new WorkoutResource($workout);
        $array = $resource->toArray(request());

        $expectedKeys = ['id', 'user_id', 'status', 'created_at', 'activities_count', 'workout_template', 'activities'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }
}
