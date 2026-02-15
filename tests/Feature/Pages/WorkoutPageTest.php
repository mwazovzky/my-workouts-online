<?php

namespace Tests\Feature\Pages;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workout_index_page_is_rendered(): void
    {
        $user = User::factory()->create();

        $workout = Workout::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workouts.index'));

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('WorkoutIndex')
                ->has('workouts')
                ->has('workouts.data')
                ->has('workouts.links')
                ->where('workouts.data.0.id', $workout->id)
                ->has('workouts.data.0.name')
                ->has('workouts.data.0.workout_template')
                ->has('workouts.data.0.activities_count')
        );
    }

    #[Test]
    public function workout_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create();

        $workout = Workout::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workouts.show', ['id' => $workout->id]));

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('WorkoutShow')
                ->has('workout')
                ->where('workout.id', $workout->id)
                ->has('workout.name')
        );
    }

    #[Test]
    public function workout_edit_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create();

        $workout = Workout::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $activity = Activity::factory()
            ->for($workout, 'workout')
            ->create();

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1]);

        $response = $this
            ->actingAs($user)
            ->get(route('workouts.edit', ['id' => $workout->id]));

        $response->assertOk();

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('WorkoutEdit')
                ->has('workout')
                ->where('workout.id', $workout->id)
                ->has('workout.name')
                ->has('workout.activities')
                ->has('workout.activities.0.sets.0.id')
        );
    }
}
