<?php

namespace App\Services\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;

interface WorkoutLogServiceInterface
{
    /**
     * Create new workout with a reference to template for the given user.
     */
    public function createFromTemplate(User $user, int $workoutTemplateId): WorkoutLog;
}
