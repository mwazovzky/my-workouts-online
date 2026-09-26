<?php

namespace Tests\Feature\Workout;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_view_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/workouts/{$workout->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $workout->id);
    }

    #[Test]
    public function non_owner_cannot_view_workout(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->getJson("/api/v1/workouts/{$workout->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function show_returns_404_for_nonexistent_workout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/workouts/99999')->assertNotFound();
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $workout = Workout::factory()->create();

        $this->getJson("/api/v1/workouts/{$workout->id}")->assertUnauthorized();
    }
}
