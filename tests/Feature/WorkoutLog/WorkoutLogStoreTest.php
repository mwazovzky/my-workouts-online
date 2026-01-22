<?php

namespace Tests\Feature\WorkoutLog;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-01-01 10:00:00');
    }

    #[Test]
    public function start_creates_workout_log_and_copies_activities_and_sets_from_template(): void
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
                ['order' => 1, 'repetitions' => 10, 'weight' => 20],
                ['order' => 2, 'repetitions' => 8,  'weight' => 22],
            ))
            ->create();

        $response = $this->actingAs($user)->post(route('workout.logs.store'), [
            'workout_template_id' => $workoutTemplate->id,
        ]);

        $response->assertRedirect();
        $response->assertRedirect(route('workout.logs.edit', ['id' => WorkoutLog::latest('id')->first()->id]));

        $workout = WorkoutLog::latest('id')->first();
        $activity = $workout->activities->first();

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workout->id,
            'workout_template_id' => $workoutTemplate->id,
        ]);

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'workout_id' => $workout->id,
            'workout_type' => 'workout_log',
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 10,
            'weight' => 20,
        ]);

        $this->assertDatabaseHas('sets', [
            'activity_id' => $activity->id,
            'order' => 2,
            'repetitions' => 8,
            'weight' => 22,
        ]);
    }

    #[Test]
    public function requires_workout_template_id_with_custom_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workout.logs.store'), []);

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

        $response = $this->actingAs($user)->post(route('workout.logs.store'), [
            'workout_template_id' => 9999,
        ]);

        $response->assertSessionHasErrors(['workout_template_id']);

        $this->assertSame(
            'The selected workout template could not be found.',
            session('errors')->get('workout_template_id')[0],
        );
    }
}
