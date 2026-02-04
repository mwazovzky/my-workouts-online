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

    /**
     * Create a new in-progress workout log by copying the structure and set values
     * from an existing (completed) workout log.
     */
    public function repeat(WorkoutLog $sourceWorkoutLog): WorkoutLog;

    /**
     * Delete workout log and all related activities and sets.
     */
    public function delete(WorkoutLog $workoutLog): void;
}
