<?php

namespace Tests\Feature\Activity;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_update_uses_custom_validation_messages(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $activity = Activity::factory()
            ->for($workoutLog, 'workout')
            ->create();

        $payload = [
            'sets' => [
                [
                    'order' => null,
                    'repetitions' => null,
                    'weight' => null,
                ],
            ],
        ];

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => $activity->id]), $payload);

        $response->assertSessionHasErrors([
            'sets.0.order',
            'sets.0.repetitions',
            'sets.0.weight',
        ]);

        $this->assertSame(
            'Order is required for each set',
            session('errors')->get('sets.0.order')[0],
        );
        $this->assertSame(
            'Repetitions are required for each set',
            session('errors')->get('sets.0.repetitions')[0],
        );
        $this->assertSame(
            'Weight is required for each set',
            session('errors')->get('sets.0.weight')[0],
        );
    }

    public function test_activity_update_rejects_set_ids_that_do_not_belong_to_the_activity(): void
    {
        $user = User::factory()->create();
        $workoutLog = WorkoutLog::factory()->create([
            'user_id' => $user->id,
        ]);

        $activityA = Activity::factory()
            ->for($workoutLog, 'workout')
            ->create(['order' => 1]);

        $activityB = Activity::factory()
            ->for($workoutLog, 'workout')
            ->create(['order' => 2]);

        $foreignSet = Set::factory()
            ->for($activityB, 'activity')
            ->create(['order' => 1]);

        $response = $this
            ->actingAs($user)
            ->patch(route('activities.update', ['activity' => $activityA->id]), [
                'sets' => [
                    ['id' => $foreignSet->id, 'order' => 1, 'repetitions' => 10, 'weight' => 50],
                ],
            ]);

        $response->assertSessionHasErrors([
            'sets.0.id',
        ]);
    }
}
