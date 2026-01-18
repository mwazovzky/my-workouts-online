<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkoutTemplatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_template_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('workout.templates.show', ['id' => 1]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutTemplateShow')
            ->where('id', 1)
        );
    }
}
