<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProgramStoreRequest;
use App\Http\Requests\Admin\ProgramUpdateRequest;
use App\Http\Resources\AdminProgramResource;
use App\Models\Program;
use App\Services\Admin\ProgramServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProgramController extends Controller
{
    public function __construct(private readonly ProgramServiceInterface $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Program::class);

        $programs = Program::query()
            ->withCount('workoutTemplates')
            ->orderBy('id')
            ->get();

        return AdminProgramResource::collection($programs);
    }

    public function show(Program $program): AdminProgramResource
    {
        $this->authorize('viewAny', Program::class);

        $program->load([
            'workoutTemplates' => fn ($query) => $query->orderBy('program_workout_template.id'),
        ]);

        return new AdminProgramResource($program);
    }

    public function store(ProgramStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Program::class);

        $program = $this->service->create($request->validated());

        return (new AdminProgramResource($program))->response()->setStatusCode(201);
    }

    public function update(ProgramUpdateRequest $request, Program $program): AdminProgramResource
    {
        $this->authorize('update', $program);

        $program = $this->service->update($program, $request->validated());

        return new AdminProgramResource($program);
    }

    public function destroy(Program $program): Response
    {
        $this->authorize('delete', $program);

        $this->service->delete($program);

        return response()->noContent();
    }
}
