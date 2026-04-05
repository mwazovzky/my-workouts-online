<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutSaveRequest;
use App\Http\Requests\WorkoutStoreRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Services\Workout\WorkoutServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkoutController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workouts = Workout::query()
            ->ownedBy($request->user())
            ->withTemplate()
            ->withActivitiesCount()
            ->latestUpdated()
            ->paginate(20);

        return WorkoutResource::collection($workouts);
    }

    public function show(Request $request, int $id): WorkoutResource
    {
        $workout = Workout::query()
            ->ownedBy($request->user())
            ->withTemplate()
            ->withActivitiesCount()
            ->findOrFail($id);

        $workout->load([
            'activities' => fn ($query) => $query->orderBy('order'),
            'activities.sets' => fn ($query) => $query->orderBy('order'),
            'activities.exercise.equipment.translations',
            'activities.exercise.categories.translations',
            'activities.exercise.translations',
        ]);

        return new WorkoutResource($workout);
    }

    public function store(WorkoutStoreRequest $request, WorkoutServiceInterface $service): JsonResponse
    {
        $workout = $service->createFromTemplate($request->user(), $request->validated()['workout_template_id']);

        return (new WorkoutResource($workout))->response()->setStatusCode(201);
    }

    public function save(WorkoutSaveRequest $request, Workout $workout, WorkoutServiceInterface $service): WorkoutResource
    {
        $this->authorize('save', $workout);

        $workout = $service->save($workout, $request->validated());

        return new WorkoutResource($workout);
    }

    public function complete(Workout $workout): WorkoutResource
    {
        $this->authorize('complete', $workout);

        $workout->status = WorkoutStatus::Completed;
        $workout->save();

        return new WorkoutResource($workout);
    }

    public function repeat(Workout $workout, WorkoutServiceInterface $service): WorkoutResource
    {
        $this->authorize('repeat', $workout);

        $newWorkout = $service->repeat($workout);

        return new WorkoutResource($newWorkout);
    }

    public function destroy(Workout $workout, WorkoutServiceInterface $service): JsonResponse
    {
        $this->authorize('delete', $workout);

        $service->delete($workout);

        return response()->json(null, 204);
    }
}
