<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutTemplateResource;
use App\Models\WorkoutTemplate;

class WorkoutTemplateController extends Controller
{
    public function show(int $id): WorkoutTemplateResource
    {
        $workoutTemplate = WorkoutTemplate::query()
            ->with([
                'activities' => fn ($query) => $query->orderBy('order'),
                'activities.sets' => fn ($query) => $query->orderBy('order'),
                'activities.exercise',
                'activities.exercise.equipment',
                'activities.exercise.categories',
            ])
            ->findOrFail($id);

        return new WorkoutTemplateResource($workoutTemplate);
    }
}
