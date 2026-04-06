<?php

namespace App\Http\Controllers;

use App\Enums\WorkoutStatus;
use App\Http\Requests\WorkoutSaveRequest;
use App\Http\Requests\WorkoutStoreRequest;
use App\Models\Workout;
use App\Services\Workout\WorkoutServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WorkoutIndex');
    }

    public function show(int $id): Response
    {
        return Inertia::render('WorkoutShow', ['id' => $id]);
    }

    public function edit(int $id): Response
    {
        return Inertia::render('WorkoutEdit', ['id' => $id]);
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

        return redirect()->back();
    }
}
