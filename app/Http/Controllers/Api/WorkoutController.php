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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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

    public function show(Request $request, Workout $workout): WorkoutResource
    {
        abort_if($workout->user_id !== $request->user()->id, 404);

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
        $this->authorize('save', $workout);

        $workout = $service->save($workout, $request->validated());

        return new WorkoutResource($workout);
    }

    public function complete(Workout $workout): WorkoutResource
    {
        $this->authorize('complete', $workout);

        $workout->status = WorkoutStatus::Completed;
        $workout->save();

        Log::info('workout.completed', [
            'user_id' => $workout->user_id,
            'workout_id' => $workout->id,
        ]);

        return new WorkoutResource($workout);
    }

    public function repeat(Workout $workout, WorkoutServiceInterface $service): JsonResponse
    {
        $this->authorize('repeat', $workout);

        $newWorkout = $service->repeat($workout);

        Log::info('workout.repeated', [
            'user_id' => $workout->user_id,
            'source_id' => $workout->id,
            'new_id' => $newWorkout->id,
        ]);

        return (new WorkoutResource($newWorkout))->response()->setStatusCode(201);
    }

    public function destroy(Workout $workout, WorkoutServiceInterface $service): Response
    {
        $this->authorize('delete', $workout);

        $userId = $workout->user_id;
        $service->delete($workout);

        Log::info('workout.deleted', [
            'user_id' => $userId,
            'workout_id' => $workout->id,
        ]);

        return response()->noContent();
    }
}
