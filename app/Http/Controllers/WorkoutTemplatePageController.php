<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkoutTemplateResource;
use App\Models\WorkoutTemplate;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutTemplatePageController extends Controller
{
    public function show(int $id): Response
    {
        $workoutTemplate = WorkoutTemplate::query()
            ->withTranslations()
            ->with([
                'activities' => fn($query) => $query->orderBy('order'),
                'activities.sets' => fn($query) => $query->orderBy('order'),
                'activities.exercise.translations',
                'activities.exercise.equipment.translations',
                'activities.exercise.categories.translations',
            ])
            ->findOrFail($id);

        return Inertia::render('WorkoutTemplateShow', [
            'workout' => (new WorkoutTemplateResource($workoutTemplate))->resolve(),
        ]);
    }
}
