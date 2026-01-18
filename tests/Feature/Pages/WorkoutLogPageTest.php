<?php

namespace Tests\Feature\Pages;

use App\Models\User;
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

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogIndex')
        );
    }

    public function test_workout_log_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.show', ['id' => 1]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogShow')
            ->where('id', 1)
        );
    }

    public function test_workout_log_edit_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.logs.edit', ['id' => 1]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutLogEdit')
            ->where('id', 1)
        );
    }
}
