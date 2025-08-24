<?php

namespace Tests\Feature\Api\WorkoutTemplate;

use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutTemplate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutTemplateShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-01-01 10:00:00');
    }

    public function test_it_returns_workout_template_details(): void
    {
        $user = User::factory()->create();

        $workoutTemplate = WorkoutTemplate::factory()->create();

        $activity = Activity::factory()
            ->for($workoutTemplate, 'workout')
            ->create();

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 1, 'repetitions' => 5, 'weight' => 50]);

        Set::factory()
            ->for($activity, 'activity')
            ->create(['order' => 2, 'repetitions' => 5, 'weight' => 50]);

        $response = $this->actingAs($user)->getJson(route('api.workout.templates.show', ['workout_template' => $workoutTemplate->id]));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $workoutTemplate->id,
                'name' => $workoutTemplate->name,
                'description' => $workoutTemplate->description,
                'weekday' => null,
                'activities' => [
                    [
                        'id' => $activity->id,
                        'exercise_name' => $activity->exercise->name,
                        'sets' => [
                            [
                                // 'id' => 1,
                                'order' => 1,
                                'repetitions' => 5,
                                'weight' => 50,
                            ],
                            [
                                // 'id' => 2,
                                'order' => 2,
                                'repetitions' => 5,
                                'weight' => 50,
                            ],
                        ],
                    ],
                ],
                'activities_count' => 1,
                'created_at' => now()->toJSON(),
                'updated_at' => now()->toJSON(),
            ],
        ]);
    }

    public function test_it_returns_404_if_workout_template_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('api.workout.templates.show', 9999));

        $response->assertNotFound();
    }
}
