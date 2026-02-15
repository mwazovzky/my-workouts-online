<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Set;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_transforms_activity_to_correct_json_structure(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Bench Press']);
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        $resource = new ActivityResource($activity);
        $array = $resource->toArray(request());

        $this->assertIsArray($array);
        $this->assertEquals($activity->id, $array['id']);
        $this->assertEquals($exercise->id, $array['exercise_id']);
    }

    #[Test]
    public function resource_includes_exercise_name_when_exercise_loaded(): void
    {
        $equipment = Equipment::factory()->create(['name' => 'Barbell']);
        $category = Category::query()->create(['name' => 'Strength']);

        $exercise = Exercise::factory()->create([
            'name' => 'Squat',
            'equipment_id' => $equipment->id,
        ]);

        $exercise->categories()->attach($category);
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        // Load activity with exercise relationship (including equipment + categories)
        $activityWithExercise = Activity::with('exercise.equipment', 'exercise.categories')->find($activity->id);
        $resource = new ActivityResource($activityWithExercise);
        $array = $resource->toArray(request());

        $this->assertEquals('Squat', $array['exercise_name']);
        $this->assertEquals($exercise->rest_time_seconds, $array['rest_time_seconds']);
        $this->assertSame('Barbell', $array['exercise_equipment_name']);
        $this->assertSame(['Strength'], $array['exercise_category_names']);
    }

    #[Test]
    public function resource_handles_exercise_name_when_exercise_not_loaded(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Deadlift']);
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        // Load activity WITHOUT exercise relationship
        $activityWithoutExercise = Activity::find($activity->id);
        $resource = new ActivityResource($activityWithoutExercise);
        $array = $resource->toArray(request());

        // exercise_name should be null when relationship not loaded
        $this->assertNull($array['exercise_name']);
        $this->assertNull($array['rest_time_seconds']);
        $this->assertNull($array['exercise_equipment_name']);
        $this->assertSame([], $array['exercise_category_names']);
    }

    #[Test]
    public function sets_collection_properly_transformed(): void
    {
        $workout = Workout::factory()->create();
        $activity = Activity::factory()->create([
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        Set::factory()
            ->for($activity, 'activity')
            ->count(3)
            ->sequence(
                ['order' => 1],
                ['order' => 2],
                ['order' => 3]
            )
            ->create();

        // Load activity with sets
        $activityWithSets = Activity::with('sets')->find($activity->id);
        $resource = new ActivityResource($activityWithSets);
        $array = $resource->toArray(request());

        $this->assertCount(3, $array['sets']);
        $this->assertIsArray($array['sets']);

        // Verify each set has the expected structure
        foreach ($array['sets'] as $set) {
            $this->assertArrayHasKey('id', $set);
            $this->assertArrayHasKey('order', $set);
            $this->assertArrayHasKey('repetitions', $set);
            $this->assertArrayHasKey('weight', $set);
            $this->assertArrayHasKey('is_completed', $set);
        }
    }

    #[Test]
    public function sets_empty_when_not_loaded(): void
    {
        $workout = Workout::factory()->create();
        $activity = Activity::factory()->create([
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        Set::factory()
            ->for($activity, 'activity')
            ->count(2)
            ->sequence(
                ['order' => 1],
                ['order' => 2]
            )
            ->create();

        // Load activity WITHOUT sets relationship
        $activityWithoutSets = Activity::find($activity->id);
        $resource = new ActivityResource($activityWithoutSets);
        $array = $resource->toArray(request());

        $this->assertEmpty($array['sets']);
    }

    #[Test]
    public function polymorphic_workout_relationship_handled_correctly(): void
    {
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        $resource = new ActivityResource($activity);
        $array = $resource->toArray(request());

        // Resource should not expose internal polymorphic fields
        $this->assertArrayNotHasKey('workout_id', $array);
        $this->assertArrayNotHasKey('workout_type', $array);
        $this->assertArrayNotHasKey('workout', $array);
    }

    #[Test]
    public function resource_includes_all_expected_keys(): void
    {
        $exercise = Exercise::factory()->create();
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        $resource = new ActivityResource($activity);
        $array = $resource->toArray(request());

        $expectedKeys = ['id', 'exercise_id', 'exercise_name', 'rest_time_seconds', 'exercise_equipment_name', 'exercise_category_names', 'sets'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }
}
