<?php

namespace App\Http\Controllers;

use App\Models\WorkoutTemplate;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutTemplatePageController extends Controller
{
    public function show(int $id): Response
    {
        $workoutTemplate = WorkoutTemplate::query()
            ->with(['activities.sets', 'activities.exercise'])
            ->findOrFail($id);

        return Inertia::render('WorkoutTemplateShow', [
            'workout' => $workoutTemplate,
        ]);
    }
}
