<?php

namespace App\Services\WorkoutLog;

use App\Enums\WorkoutLogStatus;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;

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
