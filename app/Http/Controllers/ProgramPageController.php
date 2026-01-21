<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProgramResource;
use App\Http\Resources\WorkoutTemplateResource;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramPageController extends Controller
{
    public function index(): Response
    {
        $programs = Program::query()
            ->with('users')
            ->get();

        return Inertia::render('ProgramIndex', [
            'programs' => ProgramResource::collection($programs)->resolve(),
        ]);
    }

    public function show(int $id): Response
    {
        $program = Program::query()
            ->with('users')
            ->findOrFail($id);

        return Inertia::render('ProgramShow', [
            'program' => (new ProgramResource($program))->resolve(),
            'workouts' => Inertia::defer(fn () => WorkoutTemplateResource::collection($program->workoutTemplates)->resolve()),
        ]);
    }

    public function enroll(Request $request, Program $program): RedirectResponse
    {
        $user = $request->user();

        $program->users()->syncWithoutDetaching([$user->id]);

        return redirect()->route('programs.show', ['id' => $program->id]);
    }
}
