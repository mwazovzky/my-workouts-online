<?php

namespace Tests\Feature\Pages;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workout_log_index_page_is_rendered(): void
    {
        $user = User::factory()->create();

        $log = WorkoutLog::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogIndex')
            ->has('logs')
            ->where('logs.0.id', $log->id)
            ->has('logs.0.workout_template')
            ->has('logs.0.activities_count')
        );
    }

    #[Test]
    public function workout_log_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create();

        $log = WorkoutLog::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.show', ['id' => $log->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogShow')
            ->has('workoutLog')
            ->where('workoutLog.id', $log->id)
        );
    }

    #[Test]
    public function workout_log_edit_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create();

        $log = WorkoutLog::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $activity = Activity::factory()
            ->for($log, 'workout')
            ->create();

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.edit', ['id' => $log->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogEdit')
            ->has('workoutLog')
            ->where('workoutLog.id', $log->id)
            ->has('workoutLog.activities')
            ->has('workoutLog.activities.0.sets.0.id')
        );
    }
}
