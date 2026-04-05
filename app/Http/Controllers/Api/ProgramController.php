<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\WorkoutTemplateResource;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgramController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $programs = Program::query()
            ->withCount(['users' => fn ($query) => $query->where('users.id', $request->user()->id)])
            ->get();

        return ProgramResource::collection($programs);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $program = Program::query()
            ->withCount(['users' => fn ($query) => $query->where('users.id', $request->user()->id)])
            ->findOrFail($id);

        $workoutTemplates = $program->workoutTemplates()->get();

        return response()->json([
            'data' => array_merge(
                (new ProgramResource($program))->resolve(),
                ['workout_templates' => WorkoutTemplateResource::collection($workoutTemplates)->resolve()]
            ),
        ]);
    }

    public function enroll(Request $request, Program $program): JsonResponse
    {
        $program->users()->syncWithoutDetaching([$request->user()->id]);

        return response()->json(null, 204);
    }
}
