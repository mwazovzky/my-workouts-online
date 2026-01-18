<?php

namespace App\Http\Controllers;

use App\Models\WorkoutLog;
use App\QueryBuilders\WorkoutLogQueryBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutLogPageController extends Controller
{
    public function index(Request $request, WorkoutLogQueryBuilder $query): Response
    {
        $user = $request->user();

        $logs = $query
            ->for($user)
            ->get();

        return Inertia::render('WorkoutLogIndex', [
            'logs' => $logs,
        ]);
    }

    public function show(int $id): Response
    {
        $workoutLog = WorkoutLog::query()
            ->with(['workoutTemplate', 'activities.sets', 'activities.exercise'])
            ->findOrFail($id);

        return Inertia::render('WorkoutLogShow', [
            'workoutLog' => $workoutLog,
        ]);
    }

    public function edit(int $id): Response
    {
        $workoutLog = WorkoutLog::query()
            ->with(['workoutTemplate', 'activities.sets', 'activities.exercise'])
            ->findOrFail($id);

        return Inertia::render('WorkoutLogEdit', [
            'workoutLog' => $workoutLog,
        ]);
    }
}
