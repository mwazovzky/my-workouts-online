<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\QueryBuilders\ProgramQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ProgramController extends Controller
{
    public function index(ProgramQueryBuilder $query): JsonResource
    {
        $programs = $query->with('users')->get();

        return ProgramResource::collection($programs);
    }

    public function show(Program $program): JsonResource
    {
        $program->load(['users', 'workoutTemplates']);

        return new ProgramResource($program);
    }

    public function enroll(Request $request, Program $program): Response
    {
        $user = $request->user();

        $program->users()->syncWithoutDetaching([$user->id]);

        return response()->noContent();
    }
}
