<?php

namespace App\Services\WorkoutLog;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;

class WorkoutLogService
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
                'status' => 'in_progress',
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
}
