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
     * Save (sync) the full set of activities and sets for a workout.
     *
     * @param  array{activities: array<int, array{id?: int|null, exercise_id: int, order: int, sets: array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric, is_completed?: bool}>}>}  $data
     */
    public function save(Workout $workout, array $data): Workout;

    /**
     * Delete workout and all related activities and sets.
     */
    public function delete(Workout $workout): void;
}
