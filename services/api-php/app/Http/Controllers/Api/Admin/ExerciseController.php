<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExerciseStoreRequest;
use App\Http\Requests\Admin\ExerciseUpdateRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Services\Admin\ExerciseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ExerciseController extends Controller
{
    public function __construct(private readonly ExerciseServiceInterface $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Exercise::class);

        $exercises = Exercise::query()
            ->with(['categories', 'equipment'])
            ->orderBy('id')
            ->get();

        return ExerciseResource::collection($exercises);
    }

    public function show(Exercise $exercise): ExerciseResource
    {
        $this->authorize('viewAny', Exercise::class);

        $exercise->load(['categories', 'equipment']);

        return new ExerciseResource($exercise);
    }

    public function store(ExerciseStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Exercise::class);

        $exercise = $this->service->create($request->validated());

        return (new ExerciseResource($exercise))->response()->setStatusCode(201);
    }

    public function update(ExerciseUpdateRequest $request, Exercise $exercise): ExerciseResource
    {
        $this->authorize('update', $exercise);

        $exercise = $this->service->update($exercise, $request->validated());

        return new ExerciseResource($exercise);
    }

    public function destroy(Exercise $exercise): Response
    {
        $this->authorize('delete', $exercise);

        $this->service->delete($exercise);

        return response()->noContent();
    }
}
