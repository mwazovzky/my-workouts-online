<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivitySetCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_mark_a_set_as_completed(): void
    {
        $user = User::factory()->create();

        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $activity = Activity::factory()->create([
            'workout_type' => WorkoutLog::class,
            'workout_id' => $workoutLog->id,
        ]);

        $set = Set::factory()->create([
            'activity_id' => $activity->id,
            'order' => 1,
            'repetitions' => 8,
            'weight' => 50,
            'is_completed' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => $activity->id]), [
                'sets' => [
                    [
                        'id' => $set->id,
                        'order' => 1,
                        'repetitions' => 8,
                        'weight' => 50,
                        'is_completed' => true,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('sets', [
            'id' => $set->id,
            'is_completed' => true,
        ]);
    }
}
