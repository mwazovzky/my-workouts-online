<?php

namespace App\Services\Workout;

use App\Enums\WorkoutStatus;
use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutService implements WorkoutServiceInterface
{
    /**
     * Create new workout with a reference to template for the given user.
     */
    public function createFromTemplate(User $user, int $workoutTemplateId): Workout
    {
        $workoutTemplate = WorkoutTemplate::with('activities.sets')->findOrFail($workoutTemplateId);

        $workout = DB::transaction(function () use ($workoutTemplate, $user) {
            $workout = Workout::create([
                'workout_template_id' => $workoutTemplate->id,
                'user_id' => $user->id,
                'name' => $workoutTemplate->name,
                'status' => WorkoutStatus::InProgress,
            ]);

            foreach ($workoutTemplate->activities as $templateActivity) {
                $activity = $workout->activities()->create([
                    'exercise_id' => $templateActivity->exercise_id,
                    'order' => $templateActivity->order,
                ]);

                foreach ($templateActivity->sets as $templateActivitySet) {
                    $activity->sets()->create([
                        'order' => $templateActivitySet->order,
                        'repetitions' => $templateActivitySet->repetitions,
                        'weight' => $templateActivitySet->weight,
                    ]);
                }
            }

            return $workout;
        });

        $workout->setRelation('workoutTemplate', $workoutTemplate);

        return $workout;
    }

    /**
     * Create a new in-progress workout by copying from an existing workout.
     */
    public function repeat(Workout $sourceWorkout): Workout
    {
        $sourceWorkout->loadMissing(['activities.sets']);

        return DB::transaction(function () use ($sourceWorkout) {
            $newWorkout = Workout::create([
                'workout_template_id' => null,
                'user_id' => $sourceWorkout->user_id,
                'name' => $sourceWorkout->name,
                'status' => WorkoutStatus::InProgress,
            ]);

            foreach ($sourceWorkout->activities as $sourceActivity) {
                $newActivity = $newWorkout->activities()->create([
                    'exercise_id' => $sourceActivity->exercise_id,
                    'order' => $sourceActivity->order,
                ]);

                foreach ($sourceActivity->sets as $sourceSet) {
                    $newActivity->sets()->create([
                        'order' => $sourceSet->order,
                        'repetitions' => $sourceSet->repetitions,
                        'weight' => $sourceSet->weight,
                        'is_completed' => false,
                    ]);
                }
            }

            return $newWorkout;
        });
    }

    /**
     * Save (sync) the full set of activities and sets for a workout.
     *
     * Diffs the provided payload against the database:
     * - Activities not in the payload are deleted
     * - Existing activities are updated, new ones are created
     * - Within each activity, sets are diffed the same way
     *
     * @param  array{activities: array<int, array{id?: int|null, exercise_id: int, order: int, sets: array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric, is_completed?: bool}>}>}  $data
     */
    public function save(Workout $workout, array $data): Workout
    {
        if ($workout->status === WorkoutStatus::Completed) {
            abort(422, 'Cannot update a completed workout.');
        }

        $payloadActivities = collect($data['activities']);

        DB::transaction(function () use ($workout, $payloadActivities) {
            $payloadActivityIds = $payloadActivities
                ->pluck('id')
                ->filter(fn ($id) => $id !== null)
                ->values()
                ->all();

            $this->assertActivityIdsBelongToWorkout($workout, $payloadActivityIds);
            $this->deleteActivitiesNotInPayload($workout, $payloadActivityIds);

            foreach ($payloadActivities as $activityData) {
                $activity = $this->upsertActivity($workout, $activityData);
                $this->syncSets($activity, $activityData['sets'] ?? []);
            }
        });

        return $workout->load(['activities.sets' => fn ($query) => $query->orderBy('order')]);
    }

    /**
     * Validate that all provided activity IDs belong to this workout.
     *
     * @param  array<int, int>  $activityIds
     */
    private function assertActivityIdsBelongToWorkout(Workout $workout, array $activityIds): void
    {
        if (empty($activityIds)) {
            return;
        }

        $validCount = Activity::query()
            ->where('workout_type', 'workout')
            ->where('workout_id', $workout->id)
            ->whereIn('id', $activityIds)
            ->count();

        if ($validCount !== count($activityIds)) {
            throw ValidationException::withMessages([
                'activities' => 'One or more activities do not belong to this workout.',
            ]);
        }
    }

    /**
     * Delete activities not present in the payload.
     *
     * @param  array<int, int>  $keepActivityIds
     */
    private function deleteActivitiesNotInPayload(Workout $workout, array $keepActivityIds): void
    {
        $query = $workout->activities();

        if (! empty($keepActivityIds)) {
            $query->whereNotIn('id', $keepActivityIds);
        }

        // Delete sets for removed activities, then the activities themselves.
        $activityIdsToDelete = $query->pluck('id');

        if ($activityIdsToDelete->isNotEmpty()) {
            Set::query()->whereIn('activity_id', $activityIdsToDelete)->delete();
            Activity::query()->whereIn('id', $activityIdsToDelete)->delete();
        }
    }

    /**
     * Create or update a single activity.
     *
     * @param  array{id?: int|null, exercise_id: int, order: int}  $data
     */
    private function upsertActivity(Workout $workout, array $data): Activity
    {
        $activityId = $data['id'] ?? null;

        if ($activityId !== null) {
            $activity = Activity::findOrFail($activityId);
            $activity->update([
                'exercise_id' => $data['exercise_id'],
                'order' => $data['order'],
            ]);

            return $activity;
        }

        return $workout->activities()->create([
            'exercise_id' => $data['exercise_id'],
            'order' => $data['order'],
        ]);
    }

    /**
     * Sync sets for an activity: delete missing, upsert existing, create new.
     *
     * @param  array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric, is_completed?: bool}>  $setsPayload
     */
    private function syncSets(Activity $activity, array $setsPayload): void
    {
        $sets = collect($setsPayload)->map(fn (array $set) => [
            'id' => isset($set['id']) && $set['id'] !== null ? (int) $set['id'] : null,
            'order' => (int) $set['order'],
            'repetitions' => (int) $set['repetitions'],
            'weight' => $set['weight'],
            'is_completed' => (bool) ($set['is_completed'] ?? false),
        ]);

        $keepSetIds = $sets->pluck('id')->filter(fn ($id) => $id !== null)->values()->all();

        $this->assertSetIdsBelongToActivity($activity, $keepSetIds);

        // Delete sets not in payload
        $deleteQuery = Set::query()->where('activity_id', $activity->id);
        if (! empty($keepSetIds)) {
            $deleteQuery->whereNotIn('id', $keepSetIds);
        }
        $deleteQuery->delete();

        // Clear orders to avoid unique constraint violations during upsert
        if (! empty($keepSetIds)) {
            Set::query()
                ->where('activity_id', $activity->id)
                ->whereIn('id', $keepSetIds)
                ->update(['order' => DB::raw('-id')]);
        }

        // Upsert existing sets
        [$updates, $creates] = $sets->partition(fn ($set) => $set['id'] !== null);

        if ($updates->isNotEmpty()) {
            Set::upsert(
                $updates->map(fn ($set) => [
                    'id' => $set['id'],
                    'activity_id' => $activity->id,
                    'order' => $set['order'],
                    'repetitions' => $set['repetitions'],
                    'weight' => $set['weight'],
                    'is_completed' => $set['is_completed'],
                ])->all(),
                uniqueBy: ['id'],
                update: ['order', 'repetitions', 'weight', 'is_completed']
            );
        }

        // Create new sets
        if ($creates->isNotEmpty()) {
            $activity->sets()->createMany(
                $creates->map(fn ($set) => [
                    'order' => $set['order'],
                    'repetitions' => $set['repetitions'],
                    'weight' => $set['weight'],
                    'is_completed' => $set['is_completed'],
                ])->all()
            );
        }
    }

    /**
     * Validate that all provided set IDs belong to the given activity.
     *
     * @param  array<int, int>  $setIds
     */
    private function assertSetIdsBelongToActivity(Activity $activity, array $setIds): void
    {
        if (empty($setIds)) {
            return;
        }

        $validCount = Set::query()
            ->where('activity_id', $activity->id)
            ->whereIn('id', $setIds)
            ->count();

        if ($validCount !== count($setIds)) {
            throw ValidationException::withMessages([
                'sets' => 'One or more sets do not belong to the activity.',
            ]);
        }
    }

    /**
     * Delete workout and all related activities and sets.
     */
    public function delete(Workout $workout): void
    {
        DB::transaction(function () use ($workout) {
            $workout->activities()->delete();
            $workout->delete();
        });
    }
}
