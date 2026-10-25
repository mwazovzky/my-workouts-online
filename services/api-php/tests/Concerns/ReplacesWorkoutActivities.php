<?php

namespace Tests\Concerns;

use App\Http\WorkoutETag;
use App\Models\Workout;
use Illuminate\Testing\TestResponse;

trait ReplacesWorkoutActivities
{
    /**
     * `PUT /workouts/{id}/activities` with `If-Match` set to the workout's current `ETag`,
     * as an editor that loaded the latest version would send it.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function putActivities(int $workoutId, array $payload): TestResponse
    {
        $workout = Workout::query()->find($workoutId);

        return $this->putJson("/api/v1/workouts/{$workoutId}/activities", $payload, [
            'If-Match' => $workout ? WorkoutETag::for($workout) : '"0-0"',
        ]);
    }
}
