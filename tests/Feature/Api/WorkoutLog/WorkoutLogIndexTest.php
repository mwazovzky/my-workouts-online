<?php

namespace Tests\Feature\Api\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_current_users_workout_logs(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        WorkoutLog::factory()
            ->count(2)
            ->create([
                'user_id' => $user->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        WorkoutLog::factory()
            ->count(3)
            ->create([
                'user_id' => $other->id,
                'workout_template_id' => WorkoutTemplate::factory(),
            ]);

        $response = $this->actingAs($user)->getJson(route('api.workout.logs.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'workout_template_name',
                    'workout_template_description',
                    'user_id',
                    'status',
                    'activities_count',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }
}
