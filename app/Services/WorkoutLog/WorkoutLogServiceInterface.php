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
     * Save (sync) the full set of activities and sets for a workout log.
     *
     * @param  array{activities: array<int, array{id?: int|null, exercise_id: int, order: int, sets: array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric, is_completed?: bool}>}>}  $data
     */
    public function save(WorkoutLog $workoutLog, array $data): WorkoutLog;

    /**
     * Delete workout log and all related activities and sets.
     */
    public function delete(WorkoutLog $workoutLog): void;
}
