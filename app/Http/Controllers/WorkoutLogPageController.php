<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkoutLogStoreRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\WorkoutLogResource;
use App\Models\WorkoutLog;
use App\Services\WorkoutLog\WorkoutLogServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutLogPageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $logs = WorkoutLog::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->latestUpdated()
            ->get();

        return Inertia::render('WorkoutLogIndex', [
            'logs' => WorkoutLogResource::collection($logs)->resolve(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();

        $workoutLog = WorkoutLog::query()
            ->ownedBy($user)
            ->findOrFail($id);

        return Inertia::render('WorkoutLogShow', [
            'workoutLog' => (new WorkoutLogResource($workoutLog))->resolve(),
            'activities' => Inertia::defer(fn () => ActivityResource::collection($workoutLog->activities()->with(['sets', 'exercise'])->get())->resolve()),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $request->user();

        $workoutLog = WorkoutLog::query()
            ->ownedBy($user)
            ->with(['workoutTemplate', 'activities.sets', 'activities.exercise'])
            ->findOrFail($id);

        return Inertia::render('WorkoutLogEdit', [
            'workoutLog' => (new WorkoutLogResource($workoutLog))->resolve(),
        ]);
    }

    public function store(WorkoutLogStoreRequest $request, WorkoutLogServiceInterface $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $workoutLog = $service->createFromTemplate($user, $data['workout_template_id']);

        return redirect()->route('workout.logs.edit', ['id' => $workoutLog->id]);
    }

    public function complete(WorkoutLog $workoutLog): RedirectResponse
    {
        $this->authorize('complete', $workoutLog);

        $workoutLog->status = 'completed';
        $workoutLog->save();

        return redirect()->route('workout.logs.edit', ['id' => $workoutLog->id]);
    }

    public function destroy(WorkoutLog $workoutLog): RedirectResponse
    {
        $this->authorize('delete', $workoutLog);

        $workoutLog->delete();

        return redirect()->route('workout.logs.index');
    }
}
