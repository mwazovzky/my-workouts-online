<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\Eloquent\Relations\Relation;

class ActivityPolicy
{
    public function update(User $user, Activity $activity): bool
    {
        $workoutType = Relation::getMorphedModel($activity->workout_type) ?? $activity->workout_type;

        if ($workoutType !== WorkoutLog::class) {
            return false;
        }

        /** @var \App\Models\WorkoutLog|null $workoutLog */
        $workoutLog = $activity->workout;

        return $workoutLog?->user_id === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        $workoutType = Relation::getMorphedModel($activity->workout_type) ?? $activity->workout_type;

        if ($workoutType !== WorkoutLog::class) {
            return false;
        }

        /** @var \App\Models\WorkoutLog|null $workoutLog */
        $workoutLog = $activity->workout;

        return $workoutLog?->user_id === $user->id;
    }
}
