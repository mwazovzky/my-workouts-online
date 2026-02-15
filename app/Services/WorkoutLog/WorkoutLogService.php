<?php

namespace App\Services\WorkoutLog;

use App\Enums\WorkoutLogStatus;
use App\Models\Activity;
use App\Models\Set;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutLogService implements WorkoutLogServiceInterface
{
    /**
     * Create new workout with a reference to template for the given user.
     */
    public function createFromTemplate(User $user, int $workoutTemplateId): WorkoutLog
    {
        $workoutTemplate = WorkoutTemplate::with('activities.sets')->findOrFail($workoutTemplateId);

        $workoutLog = DB::transaction(function () use ($workoutTemplate, $user) {
            $workoutLog = WorkoutLog::create([
                'workout_template_id' => $workoutTemplate->id,
                'user_id' => $user->id,
                'name' => $workoutTemplate->name,
                'status' => WorkoutLogStatus::InProgress,
            ]);

            foreach ($workoutTemplate->activities as $templateActivity) {
                $activity = $workoutLog->activities()->create([
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

            return $workoutLog;
        });

        $workoutLog->setRelation('workoutTemplate', $workoutTemplate);

        return $workoutLog;
    }

    /**
     * Create a new in-progress workout log by copying from an existing workout log.
     */
    public function repeat(WorkoutLog $sourceWorkoutLog): WorkoutLog
    {
        $sourceWorkoutLog->loadMissing(['activities.sets']);

        return DB::transaction(function () use ($sourceWorkoutLog) {
            $newWorkoutLog = WorkoutLog::create([
                'workout_template_id' => null,
                'user_id' => $sourceWorkoutLog->user_id,
                'name' => $sourceWorkoutLog->name,
                'status' => WorkoutLogStatus::InProgress,
            ]);

            foreach ($sourceWorkoutLog->activities as $sourceActivity) {
                $newActivity = $newWorkoutLog->activities()->create([
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

            return $newWorkoutLog;
        });
    }

    /**
     * Save (sync) the full set of activities and sets for a workout log.
     *
     * Diffs the provided payload against the database:
     * - Activities not in the payload are deleted
     * - Existing activities are updated, new ones are created
     * - Within each activity, sets are diffed the same way
     *
     * @param  array{activities: array<int, array{id?: int|null, exercise_id: int, order: int, sets: array<int, array{id?: int|null, order: int, repetitions: int, weight: numeric, is_completed?: bool}>}>}  $data
     */
    public function save(WorkoutLog $workoutLog, array $data): WorkoutLog
    {
        if ($workoutLog->status === WorkoutLogStatus::Completed) {
            abort(422, 'Cannot update a completed workout.');
        }

        $payloadActivities = collect($data['activities']);

        DB::transaction(function () use ($workoutLog, $payloadActivities) {
            $payloadActivityIds = $payloadActivities
                ->pluck('id')
                ->filter(fn ($id) => $id !== null)
                ->values()
                ->all();

            $this->assertActivityIdsBelongToWorkout($workoutLog, $payloadActivityIds);
            $this->deleteActivitiesNotInPayload($workoutLog, $payloadActivityIds);

            foreach ($payloadActivities as $activityData) {
                $activity = $this->upsertActivity($workoutLog, $activityData);
                $this->syncSets($activity, $activityData['sets'] ?? []);
            }
        });

        return $workoutLog->load(['activities.sets' => fn ($query) => $query->orderBy('order')]);
    }

    /**
     * Validate that all provided activity IDs belong to this workout log.
     *
     * @param  array<int, int>  $activityIds
     */
    private function assertActivityIdsBelongToWorkout(WorkoutLog $workoutLog, array $activityIds): void
    {
        if (empty($activityIds)) {
            return;
        }

        $validCount = Activity::query()
            ->where('workout_type', 'workout_log')
            ->where('workout_id', $workoutLog->id)
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
    private function deleteActivitiesNotInPayload(WorkoutLog $workoutLog, array $keepActivityIds): void
    {
        $query = $workoutLog->activities();

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
    private function upsertActivity(WorkoutLog $workoutLog, array $data): Activity
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

        return $workoutLog->activities()->create([
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
     * Delete workout log and all related activities and sets.
     */
    public function delete(WorkoutLog $workoutLog): void
    {
        DB::transaction(function () use ($workoutLog) {
            $workoutLog->activities()->delete();
            $workoutLog->delete();
        });
    }
}
