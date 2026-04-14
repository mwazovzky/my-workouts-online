<?php

namespace Tests\Feature\Workout;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_delete_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/workouts/{$workout->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    #[Test]
    public function non_owner_cannot_delete_workout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory(),
        ]);

        $response = $this->actingAs($otherUser)->deleteJson("/api/v1/workouts/{$workout->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
        ]);
    }
}
