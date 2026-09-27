<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutTemplateResource;
use App\Models\WorkoutTemplate;

class WorkoutTemplateController extends Controller
{
    public function show(WorkoutTemplate $workoutTemplate): WorkoutTemplateResource
    {
        $workoutTemplate->load([
            'activities' => fn ($query) => $query->orderBy('order'),
            'activities.sets' => fn ($query) => $query->orderBy('order'),
            'activities.exercise',
            'activities.exercise.equipment',
            'activities.exercise.categories',
        ]);

        return new WorkoutTemplateResource($workoutTemplate);
    }
}
