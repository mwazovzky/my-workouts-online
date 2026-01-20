<?php

namespace Tests\Unit\QueryBuilders;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutLogQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_by_filters_only_user_logs_without_forcing_eager_loading(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $myLog = WorkoutLog::factory()->create(['user_id' => $user->id]);
        WorkoutLog::factory()->create(['user_id' => $other->id]);

        $result = WorkoutLog::query()->ownedBy($user)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($myLog->id, $result->first()->id);
        $this->assertFalse($result->first()->relationLoaded('workoutTemplate'));
    }

    public function test_with_template_and_with_activities_count_shape_the_result(): void
    {
        $user = User::factory()->create();
        $workoutTemplate = WorkoutTemplate::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $workoutTemplate->id,
        ]);

        $result = WorkoutLog::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals($workoutLog->id, $result->first()->id);
        $this->assertTrue($result->first()->relationLoaded('workoutTemplate'));
        $this->assertEquals(0, $result->first()->activities_count);
    }

    public function test_latest_updated_orders_results_and_is_deterministic_on_ties(): void
    {
        $user = User::factory()->create();
        $older = WorkoutLog::factory()->create(['user_id' => $user->id, 'updated_at' => now()->subDay()]);
        $newer = WorkoutLog::factory()->create(['user_id' => $user->id, 'updated_at' => now()]);

        $workoutLogs = WorkoutLog::query()->ownedBy($user)->latestUpdated()->get();

        $this->assertEquals($newer->id, $workoutLogs->first()->id);
        $this->assertEquals($older->id, $workoutLogs->last()->id);

        $sameTime = now()->subHours(3);
        $first = WorkoutLog::factory()->create(['user_id' => $user->id, 'updated_at' => $sameTime]);
        $second = WorkoutLog::factory()->create(['user_id' => $user->id, 'updated_at' => $sameTime]);

        $tied = WorkoutLog::query()
            ->ownedBy($user)
            ->whereIn('id', [$first->id, $second->id])
            ->latestUpdated()
            ->pluck('id')
            ->all();

        $this->assertSame([
            max($first->id, $second->id),
            min($first->id, $second->id),
        ], $tied);
    }
}
