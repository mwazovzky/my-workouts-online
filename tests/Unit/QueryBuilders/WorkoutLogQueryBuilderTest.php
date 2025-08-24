<?php

namespace Tests\Unit\QueryBuilders;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use App\QueryBuilders\WorkoutLogQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutLogQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_returns_workout_logs_with_template_and_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workoutTemplate = WorkoutTemplate::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $workoutTemplate->id,
        ]);

        $query = new WorkoutLogQueryBuilder();
        $result = $query->for($user)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($workoutLog->id, $result->first()->id);
        $this->assertTrue($result->first()->relationLoaded('workoutTemplate'));
        $this->assertEquals(0, $result->first()->activities_count);

        // other user should see no logs
        $resultOther = (new WorkoutLogQueryBuilder())->for($other)->get();
        $this->assertTrue($resultOther->isEmpty());
    }

    public function test_proxy_ordering_filters_and_orders_results(): void
    {
        $user = User::factory()->create();
        $older = WorkoutLog::factory()->create(['user_id' => $user->id, 'updated_at' => now()->subDay()]);
        $newer = WorkoutLog::factory()->create(['user_id' => $user->id, 'updated_at' => now()]);

        $query = new WorkoutLogQueryBuilder();
        $workoutLogs = $query->for($user)->get();

        $this->assertEquals($newer->id, $workoutLogs->first()->id);
        $this->assertEquals($older->id, $workoutLogs->last()->id);
    }
}
