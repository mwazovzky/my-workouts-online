<?php

namespace Tests\Unit\QueryBuilders;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkoutQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owned_by_filters_only_user_workouts_without_forcing_eager_loading(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $myWorkout = Workout::factory()->create(['user_id' => $user->id]);
        Workout::factory()->create(['user_id' => $other->id]);

        $result = Workout::query()->ownedBy($user)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($myWorkout->id, $result->first()->id);
        $this->assertFalse($result->first()->relationLoaded('workoutTemplate'));
    }

    #[Test]
    public function with_template_and_with_activities_count_shape_the_result(): void
    {
        $user = User::factory()->create();
        $workoutTemplate = WorkoutTemplate::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $workoutTemplate->id,
        ]);

        $result = Workout::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals($workout->id, $result->first()->id);
        $this->assertTrue($result->first()->relationLoaded('workoutTemplate'));
        $this->assertEquals(0, $result->first()->activities_count);
    }

    #[Test]
    public function latest_updated_orders_results_and_is_deterministic_on_ties(): void
    {
        $user = User::factory()->create();
        $older = Workout::factory()->create(['user_id' => $user->id, 'updated_at' => now()->subDay()]);
        $newer = Workout::factory()->create(['user_id' => $user->id, 'updated_at' => now()]);

        $workouts = Workout::query()->ownedBy($user)->latestUpdated()->get();

        $this->assertEquals($newer->id, $workouts->first()->id);
        $this->assertEquals($older->id, $workouts->last()->id);

        $sameTime = now()->subHours(3);
        $first = Workout::factory()->create(['user_id' => $user->id, 'updated_at' => $sameTime]);
        $second = Workout::factory()->create(['user_id' => $user->id, 'updated_at' => $sameTime]);

        $tied = Workout::query()
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
