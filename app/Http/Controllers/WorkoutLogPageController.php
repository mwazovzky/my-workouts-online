<?php

namespace App\Http\Controllers;

use App\Enums\WorkoutLogStatus;
use App\Http\Requests\WorkoutLogSaveRequest;
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

        $workouts = WorkoutLog::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->latestUpdated()
            ->paginate(20);

        $workouts->getCollection()->transform(function ($item) use ($request) {
            return (new WorkoutLogResource($item))->toArray($request);
        });

        return Inertia::render('WorkoutLogIndex', [
            'workouts' => $workouts,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();

        $workoutLog = WorkoutLog::query()
            ->ownedBy($user)
            ->withActivitiesCount()
            ->withTemplate()
            ->findOrFail($id);

        return Inertia::render('WorkoutLogShow', [
            'workoutLog' => (new WorkoutLogResource($workoutLog))->resolve(),
            'activities' => Inertia::defer(fn() => ActivityResource::collection(
                $workoutLog->activities()
                    ->with(['sets', 'exercise.equipment', 'exercise.categories'])
                    ->get()
            )->resolve()),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $request->user();

        $workoutLog = WorkoutLog::query()
            ->ownedBy($user)
            ->withActivitiesCount()
            ->with(['workoutTemplate', 'activities.sets', 'activities.exercise.equipment', 'activities.exercise.categories'])
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

    public function save(WorkoutLogSaveRequest $request, WorkoutLog $workoutLog, WorkoutLogServiceInterface $service): RedirectResponse
    {
        $this->authorize('save', $workoutLog);

        $data = $request->validated();
        $service->save($workoutLog, $data);

        return back();
    }

    public function complete(WorkoutLog $workoutLog): RedirectResponse
    {
        $this->authorize('complete', $workoutLog);

        $workoutLog->status = WorkoutLogStatus::Completed;
        $workoutLog->save();

        return redirect()->route('workout.logs.show', ['id' => $workoutLog->id]);
    }

    public function repeat(WorkoutLog $workoutLog, Request $request, WorkoutLogServiceInterface $service): RedirectResponse
    {
        $this->authorize('repeat', $workoutLog);

        $newWorkoutLog = $service->repeat($workoutLog);

        return redirect()->route('workout.logs.edit', ['id' => $newWorkoutLog->id]);
    }

    public function destroy(WorkoutLog $workoutLog, WorkoutLogServiceInterface $service): RedirectResponse
    {
        $this->authorize('delete', $workoutLog);

        $service->delete($workoutLog);

        return redirect()->route('workout.logs.index');
    }
}
