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
        $exercise = Exercise::factory()->withTranslation('name', 'Bench Press')->create();
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        $resource = new ActivityResource($activity);
        $array = $resource->toArray(request());

        $this->assertEquals($activity->id, $array['id']);
        $this->assertEquals($exercise->id, $array['exercise_id']);
    }

    #[Test]
    public function resource_includes_the_exercise_snapshot_when_exercise_loaded(): void
    {
        $equipment = Equipment::factory()->withTranslation('name', 'Barbell')->create();
        $category = Category::factory()->withTranslation('name', 'Strength')->create();

        $exercise = Exercise::factory()->withTranslation('name', 'Squat')->create([
            'equipment_id' => $equipment->id,
            'description' => 'Sit back and down, then stand up.',
        ]);

        $exercise->categories()->attach($category);
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        // Load activity with exercise relationship (including equipment + categories)
        $activityWithExercise = Activity::with('exercise.equipment.translations', 'exercise.categories.translations', 'exercise.translations')->find($activity->id);
        $array = (new ActivityResource($activityWithExercise))->resolve();

        $this->assertSame([
            'id' => $exercise->id,
            'name' => 'Squat',
            'description' => 'Sit back and down, then stand up.',
            'equipment_name' => 'Barbell',
            'effort_type' => 'repetitions',
            'difficulty_unit' => 'kilograms',
            'category_names' => ['Strength'],
            'rest_time_seconds' => $exercise->rest_time_seconds,
        ], $array['exercise']);
    }

    #[Test]
    public function the_snapshot_description_is_null_when_the_exercise_has_none(): void
    {
        $exercise = Exercise::factory()->create(['description' => null]);
        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => Workout::factory()->create()->id,
            'workout_type' => 'workout',
        ]);

        $array = (new ActivityResource($activity->load('exercise.equipment', 'exercise.categories')))->resolve();

        $this->assertArrayHasKey('description', $array['exercise']);
        $this->assertNull($array['exercise']['description']);
    }

    #[Test]
    public function the_snapshot_description_falls_back_to_english(): void
    {
        $exercise = Exercise::factory()->create(['description' => 'Keep your back straight.']);
        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => Workout::factory()->create()->id,
            'workout_type' => 'workout',
        ]);

        app()->setLocale('ru');
        $array = (new ActivityResource($activity->load('exercise.equipment', 'exercise.categories', 'exercise.translations')))->resolve();

        $this->assertSame('Keep your back straight.', $array['exercise']['description']);
    }

    #[Test]
    public function the_snapshot_of_a_retired_exercise_keeps_its_description(): void
    {
        $exercise = Exercise::factory()->create(['description' => 'Pull the bar to your chest.']);
        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => Workout::factory()->create()->id,
            'workout_type' => 'workout',
        ]);
        $exercise->delete();

        $array = (new ActivityResource(Activity::with('exercise.equipment', 'exercise.categories')->find($activity->id)))->resolve();

        $this->assertSame('Pull the bar to your chest.', $array['exercise']['description']);
    }

    #[Test]
    public function resource_omits_the_exercise_snapshot_when_exercise_not_loaded(): void
    {
        $exercise = Exercise::factory()->withTranslation('name', 'Deadlift')->create();
        $workout = Workout::factory()->create();

        $activity = Activity::factory()->create([
            'exercise_id' => $exercise->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        // Load activity WITHOUT exercise relationship
        $activityWithoutExercise = Activity::find($activity->id);
        $array = (new ActivityResource($activityWithoutExercise))->resolve();

        $this->assertSame($exercise->id, $array['exercise_id']);
        $this->assertArrayNotHasKey('exercise', $array);
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
            $this->assertArrayHasKey('effort_value', $set);
            $this->assertArrayHasKey('difficulty_value', $set);
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

        $array = (new ActivityResource($activity->load('exercise.equipment', 'exercise.categories')))->resolve();

        $this->assertSame(['id', 'exercise_id', 'exercise', 'sets'], array_keys($array));
    }
}
