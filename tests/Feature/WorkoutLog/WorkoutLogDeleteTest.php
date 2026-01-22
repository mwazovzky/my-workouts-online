<?php

namespace Tests\Feature\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutLogDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_delete_workout_log(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('workout.logs.destroy', ['workoutLog' => $workoutLog->id]));

        $response->assertRedirect(route('workout.logs.index'));

        $this->assertDatabaseMissing('workout_logs', ['id' => $workoutLog->id]);
    }

    #[Test]
    public function non_owner_cannot_delete_workout_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory(),
        ]);

        $response = $this->actingAs($otherUser)->delete(route('workout.logs.destroy', ['workoutLog' => $workoutLog->id]));

        $response->assertStatus(403);

        $this->assertDatabaseHas('workout_logs', [
            'id' => $workoutLog->id,
        ]);
    }
}
