<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $user = $request->user();

        $programs = Program::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })->get();

        return ProgramResource::collection($programs);
    }

    public function show(int $id): JsonResource|JsonResponse
    {
        $program = Program::with('workoutTemplates')->find($id);

        if (! $program) {
            return response()->json(['message' => 'Program not found'], 404);
        }

        return new ProgramResource($program);
    }
}
