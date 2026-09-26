<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_returns_template_with_activities_and_sets(): void
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create();
        $activity = Activity::factory()->for($template, 'workout')->create(['order' => 1]);
        Set::factory()->for($activity, 'activity')->create(['order' => 1, 'effort_value' => 10, 'difficulty_value' => 50]);

        $response = $this->actingAs($user)->getJson("/api/v1/workout-templates/{$template->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $template->id);
        $response->assertJsonStructure(['data' => ['id', 'name', 'activities']]);
        $response->assertJsonCount(1, 'data.activities');
        $response->assertJsonCount(1, 'data.activities.0.sets');
    }

    #[Test]
    public function show_returns_404_for_nonexistent_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/workout-templates/99999')->assertNotFound();
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $template = WorkoutTemplate::factory()->create();
        $this->getJson("/api/v1/workout-templates/{$template->id}")->assertUnauthorized();
    }
}
