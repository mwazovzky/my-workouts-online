<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $user->isAdmin();
    }
}
