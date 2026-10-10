<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkoutTemplateStoreRequest;
use App\Http\Requests\Admin\WorkoutTemplateUpdateRequest;
use App\Http\Resources\AdminWorkoutTemplateResource;
use App\Models\WorkoutTemplate;
use App\Services\Admin\WorkoutTemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkoutTemplateController extends Controller
{
    public function __construct(private readonly WorkoutTemplateServiceInterface $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkoutTemplate::class);

        $templates = WorkoutTemplate::query()
            ->withCount('activities')
            ->orderBy('id')
            ->get();

        return AdminWorkoutTemplateResource::collection($templates);
    }

    public function show(WorkoutTemplate $workoutTemplate): AdminWorkoutTemplateResource
    {
        $this->authorize('viewAny', WorkoutTemplate::class);

        $workoutTemplate->load([
            'activities' => fn ($query) => $query->orderBy('order'),
            'activities.sets' => fn ($query) => $query->orderBy('order'),
            'activities.exercise.equipment',
            'activities.exercise.categories',
        ]);

        return new AdminWorkoutTemplateResource($workoutTemplate);
    }

    public function store(WorkoutTemplateStoreRequest $request): JsonResponse
    {
        $this->authorize('create', WorkoutTemplate::class);

        $template = $this->service->create($request->validated());

        return (new AdminWorkoutTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(WorkoutTemplateUpdateRequest $request, WorkoutTemplate $workoutTemplate): AdminWorkoutTemplateResource
    {
        $this->authorize('update', $workoutTemplate);

        $template = $this->service->update($workoutTemplate, $request->validated());

        return new AdminWorkoutTemplateResource($template);
    }

    public function destroy(WorkoutTemplate $workoutTemplate): Response
    {
        $this->authorize('delete', $workoutTemplate);

        if ($workoutTemplate->programs()->exists()) {
            throw new ApiException(409, ErrorCode::InUse, __('Cannot delete a workout template that is used by a program.'));
        }

        $this->service->delete($workoutTemplate);

        return response()->noContent();
    }
}
