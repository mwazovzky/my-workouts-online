<?php

namespace App\Http\Controllers;

use App\Enums\WorkoutStatus;
use App\Http\Requests\WorkoutSaveRequest;
use App\Http\Requests\WorkoutStoreRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Services\Workout\WorkoutServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutPageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $workouts = Workout::query()
            ->ownedBy($user)
            ->withTemplate()
            ->withActivitiesCount()
            ->latestUpdated()
            ->paginate(20);

        $workouts->getCollection()->transform(function ($item) use ($request) {
            return (new WorkoutResource($item))->toArray($request);
        });

        return Inertia::render('WorkoutIndex', [
            'workouts' => $workouts,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();

        $workout = Workout::query()
            ->ownedBy($user)
            ->withActivitiesCount()
            ->withTemplate()
            ->findOrFail($id);

        return Inertia::render('WorkoutShow', [
            'workout' => (new WorkoutResource($workout))->resolve(),
            'activities' => Inertia::defer(fn () => ActivityResource::collection(
                $workout->activities()
                    ->with([
                        'sets' => fn ($query) => $query->orderBy('order'),
                        'exercise.equipment.translations',
                        'exercise.categories.translations',
                        'exercise.translations',
                    ])
                    ->orderBy('order')
                    ->get()
            )->resolve()),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $request->user();

        $workout = Workout::query()
            ->ownedBy($user)
            ->withActivitiesCount()
            ->with([
                'workoutTemplate.translations',
                'activities' => fn ($query) => $query->orderBy('order'),
                'activities.sets' => fn ($query) => $query->orderBy('order'),
                'activities.exercise.equipment.translations',
                'activities.exercise.categories.translations',
                'activities.exercise.translations',
            ])
            ->findOrFail($id);

        return Inertia::render('WorkoutEdit', [
            'workout' => (new WorkoutResource($workout))->resolve(),
        ]);
    }

    public function store(WorkoutStoreRequest $request, WorkoutServiceInterface $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $workout = $service->createFromTemplate($user, $data['workout_template_id']);

        return redirect()->route('workouts.edit', ['id' => $workout->id]);
    }

    public function save(WorkoutSaveRequest $request, Workout $workout, WorkoutServiceInterface $service): RedirectResponse
    {
        $this->authorize('save', $workout);

        $data = $request->validated();
        $service->save($workout, $data);

        return back();
    }

    public function complete(Workout $workout): RedirectResponse
    {
        $this->authorize('complete', $workout);

        $workout->status = WorkoutStatus::Completed;
        $workout->save();

        return redirect()->route('workouts.show', ['id' => $workout->id]);
    }

    public function repeat(Workout $workout, Request $request, WorkoutServiceInterface $service): RedirectResponse
    {
        $this->authorize('repeat', $workout);

        $newWorkout = $service->repeat($workout);

        return redirect()->route('workouts.edit', ['id' => $newWorkout->id]);
    }

    public function destroy(Workout $workout, WorkoutServiceInterface $service): RedirectResponse
    {
        $this->authorize('delete', $workout);

        $service->delete($workout);

        return redirect()->route('workouts.index');
    }
}
