<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkoutLogStoreRequest;
use App\Models\WorkoutLog;
use App\QueryBuilders\WorkoutLogQueryBuilder;
use App\Services\WorkoutLog\WorkoutLogService;
use Illuminate\Http\RedirectResponse;
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
            ->findOrFail($id);

        return Inertia::render('WorkoutLogShow', [
            'workoutLog' => $workoutLog,
            'activities' => Inertia::defer(fn () => $workoutLog->activities()->with(['sets', 'exercise'])->get()),
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

    public function store(WorkoutLogStoreRequest $request, WorkoutLogService $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $workoutLog = $service->createFromTemplate($user, $data['workout_template_id']);

        return redirect()->route('workout.logs.edit', ['id' => $workoutLog->id]);
    }

    public function complete(WorkoutLog $workoutLog): RedirectResponse
    {
        $workoutLog->status = 'completed';
        $workoutLog->save();

        return redirect()->route('workout.logs.edit', ['id' => $workoutLog->id]);
    }

    public function destroy(Request $request, WorkoutLog $workoutLog): RedirectResponse
    {
        if ($workoutLog->user_id !== $request->user()->id) {
            abort(403);
        }

        $workoutLog->delete();

        return redirect()->route('workout.logs.index');
    }
}
