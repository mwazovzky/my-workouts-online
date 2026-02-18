<?php

namespace Tests\Feature\Workout;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-01-01 10:00:00');
    }

    #[Test]
    public function start_creates_workout_and_copies_activities_and_sets_from_template(): void
    {
        $user = User::factory()->create();

        $workoutTemplate = WorkoutTemplate::factory()->create();

        $activity = Activity::factory()
            ->for($workoutTemplate, 'workout')
            ->create();

        Set::factory()
            ->for($activity, 'activity')
            ->count(2)
            ->state(new Sequence(
                ['order' => 1, 'effort_value' => 10, 'difficulty_value' => 20],
                ['order' => 2, 'effort_value' => 8,  'difficulty_value' => 22],
            ))
            ->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'workout_template_id' => $workoutTemplate->id,
        ]);

        $response->assertRedirect();
        $response->assertRedirect(route('workouts.edit', ['id' => Workout::latest('id')->first()->id]));

        $workout = Workout::latest('id')->first();
        $activity = $workout->activities->first();

        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'workout_template_id' => $workoutTemplate->id,
            'name' => $workoutTemplate->name,
        ]);

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout',
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 1,
            'effort_value' => 10,
            'difficulty_value' => 20,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'effort_value' => 8,
            'difficulty_value' => 22,
        ]);
    }

    #[Test]
    public function requires_workout_template_id_with_custom_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), []);

        $response->assertSessionHasErrors(['workout_template_id']);

        $this->assertSame(
            'A workout template is required to start a workout.',
            session('errors')->get('workout_template_id')[0],
        );
    }

    #[Test]
    public function requires_existing_workout_template_id_with_custom_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'workout_template_id' => 9999,
        ]);

        $response->assertSessionHasErrors(['workout_template_id']);

        $this->assertSame(
            'The selected workout template could not be found.',
            session('errors')->get('workout_template_id')[0],
        );
    }
}
