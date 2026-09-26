<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutTemplate;

class WorkoutTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WorkoutTemplate $workoutTemplate): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WorkoutTemplate $workoutTemplate): bool
    {
        return $user->isAdmin();
    }
}
