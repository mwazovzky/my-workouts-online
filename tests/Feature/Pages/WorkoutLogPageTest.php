<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkoutLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_log_index_page_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        WorkoutLog::factory()
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
        );
    }

    public function test_workout_log_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

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
        );
    }

    public function test_workout_log_edit_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $log = WorkoutLog::factory()
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.edit', ['id' => $log->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogEdit')
            ->has('workoutLog')
        );
    }
}
