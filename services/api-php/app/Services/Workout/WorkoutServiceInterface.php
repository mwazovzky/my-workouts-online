<?php

namespace App\Services\Workout;

use App\Models\User;
use App\Models\Workout;

interface WorkoutServiceInterface
{
    /**
     * Create new workout with a reference to template for the given user.
     */
    public function createFromTemplate(User $user, int $workoutTemplateId): Workout;

    /**
     * Create a new in-progress workout by copying the structure and set values
     * from an existing (completed) workout.
     */
    public function repeat(Workout $sourceWorkout): Workout;

    /**
     * Save (sync) the full set of activities and sets for a workout and bump its version.
     *
     * The precondition runs against the workout row locked for the transaction; when it
     * returns false nothing is written and a 412 `precondition_failed` problem is thrown.
     *
     * @param  array{activities: array<int, array{id?: int|null, exercise_id: int, order: int, sets: array<int, array{id?: int|null, order: int, effort_value: int, difficulty_value: numeric|null, is_completed?: bool}>}>}  $data
     * @param  (callable(Workout): bool)|null  $precondition
     */
    public function save(Workout $workout, array $data, ?callable $precondition = null): Workout;

    /**
     * Delete workout and all related activities and sets.
     */
    public function delete(Workout $workout): void;
}
