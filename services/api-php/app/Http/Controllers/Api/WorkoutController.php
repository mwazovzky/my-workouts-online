<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutIndexRequest;
use App\Http\Requests\WorkoutSaveRequest;
use App\Http\Requests\WorkoutStoreRequest;
use App\Http\Resources\WorkoutCollection;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Services\Workout\WorkoutServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WorkoutController extends Controller
{
    public function index(WorkoutIndexRequest $request): WorkoutCollection
    {
        $workouts = Workout::query()
            ->ownedBy($request->user())
            ->withTemplate()
            ->withActivitiesCount()
            ->latestUpdated()
            ->paginate($request->perPage())
            ->withQueryString();

        return new WorkoutCollection($workouts);
    }

    public function show(Request $request, Workout $workout): WorkoutResource
    {
        $this->ownedOrFail($request, $workout);

        $workout->load([
            'workoutTemplate',
            'activities' => fn ($query) => $query->orderBy('order'),
            'activities.sets' => fn ($query) => $query->orderBy('order'),
            'activities.exercise.equipment.translations',
            'activities.exercise.categories.translations',
            'activities.exercise.translations',
        ]);

        $workout->loadCount('activities');

        return new WorkoutResource($workout);
    }

    public function store(WorkoutStoreRequest $request, WorkoutServiceInterface $service): JsonResponse
    {
        $workout = $service->createFromTemplate($request->user(), $request->validated()['workout_template_id']);

        Log::info('workout.started', [
            'user_id' => $request->user()->id,
            'workout_id' => $workout->id,
            'template_id' => $request->validated()['workout_template_id'],
        ]);

        return (new WorkoutResource($workout))->response()->setStatusCode(201);
    }

    public function save(WorkoutSaveRequest $request, Workout $workout, WorkoutServiceInterface $service): WorkoutResource
    {
        $this->ownedOrFail($request, $workout);
        $this->authorize('save', $workout);

        $workout = $service->save($workout, $request->validated());

        return new WorkoutResource($workout);
    }

    public function complete(Request $request, Workout $workout): WorkoutResource
    {
        $this->ownedOrFail($request, $workout);
        $this->authorize('complete', $workout);

        $workout->status = WorkoutStatus::Completed;
        $workout->save();

        Log::info('workout.completed', [
            'user_id' => $workout->user_id,
            'workout_id' => $workout->id,
        ]);

        return new WorkoutResource($workout);
    }

    public function repeat(Request $request, Workout $workout, WorkoutServiceInterface $service): JsonResponse
    {
        $this->ownedOrFail($request, $workout);
        $this->authorize('repeat', $workout);

        $newWorkout = $service->repeat($workout);

        Log::info('workout.repeated', [
            'user_id' => $workout->user_id,
            'source_id' => $workout->id,
            'new_id' => $newWorkout->id,
        ]);

        return (new WorkoutResource($newWorkout))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Workout $workout, WorkoutServiceInterface $service): Response
    {
        $this->ownedOrFail($request, $workout);
        $this->authorize('delete', $workout);

        $userId = $workout->user_id;
        $service->delete($workout);

        Log::info('workout.deleted', [
            'user_id' => $userId,
            'workout_id' => $workout->id,
        ]);

        return response()->noContent();
    }

    /**
     * A workout the caller does not own is reported as missing on every
     * action, so an id never reveals whether it exists for someone else.
     */
    private function ownedOrFail(Request $request, Workout $workout): void
    {
        abort_if($workout->user_id !== $request->user()->id, 404);
    }
}
