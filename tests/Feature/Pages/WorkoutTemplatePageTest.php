<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutTemplatePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workout_template_show_page_is_rendered_with_id_prop(): void
    {
        $user = User::factory()->create();

        $workoutTemplate = WorkoutTemplate::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('workout.templates.show', ['id' => $workoutTemplate->id]));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutTemplateShow')
            ->where('id', $workoutTemplate->id)
        );
    }
}
