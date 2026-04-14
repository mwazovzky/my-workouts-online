<?php

namespace App\Policies;

use App\Enums\WorkoutStatus;
use App\Models\User;
use App\Models\Workout;

class WorkoutPolicy
{
    public function view(User $user, Workout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function save(User $user, Workout $workout): bool
    {
        if ($workout->user_id !== $user->id) {
            return false;
        }

        return $workout->status === WorkoutStatus::InProgress;
    }

    public function complete(User $user, Workout $workout): bool
    {
        if ($workout->user_id !== $user->id) {
            return false;
        }

        return $workout->status === WorkoutStatus::InProgress;
    }

    public function repeat(User $user, Workout $workout): bool
    {
        if ($workout->user_id !== $user->id) {
            return false;
        }

        return $workout->status === WorkoutStatus::Completed;
    }
}
