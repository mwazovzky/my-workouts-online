<?php

namespace Tests\Feature\Pages;

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

        $response = $this
            ->actingAs($user)
            ->get(route('workouts.index'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutIndex')
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

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutShow')
            ->where('id', $workout->id)
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

        $response = $this
            ->actingAs($user)
            ->get(route('workouts.edit', ['id' => $workout->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutEdit')
            ->where('id', $workout->id)
        );
    }
}
