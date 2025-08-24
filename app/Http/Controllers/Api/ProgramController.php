<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\QueryBuilders\ProgramQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramController extends Controller
{
    public function index(Request $request, ProgramQueryBuilder $query): JsonResource
    {
        $user = $request->user();
        $programs = $query->for($user)->get();

        return ProgramResource::collection($programs);
    }

    public function show(Program $program): JsonResource
    {
        $program->load('workoutTemplates');

        return new ProgramResource($program);
    }
}
