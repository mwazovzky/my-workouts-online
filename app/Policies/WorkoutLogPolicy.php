<?php

namespace App\Policies;

use App\Enums\WorkoutLogStatus;
use App\Models\User;
use App\Models\WorkoutLog;

class WorkoutLogPolicy
{
    public function delete(User $user, WorkoutLog $workoutLog): bool
    {
        return $workoutLog->user_id === $user->id;
    }

    public function complete(User $user, WorkoutLog $workoutLog): bool
    {
        if ($workoutLog->user_id !== $user->id) {
            return false;
        }

        return $workoutLog->status === WorkoutLogStatus::InProgress;
    }

    public function repeat(User $user, WorkoutLog $workoutLog): bool
    {
        if ($workoutLog->user_id !== $user->id) {
            return false;
        }

        return $workoutLog->status === WorkoutLogStatus::Completed;
    }
}
