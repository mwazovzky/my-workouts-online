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
    public function index(Request $request): Response
    {
        $user = $request->user();

        $programs = Program::query()
            ->withCount(['users' => fn ($query) => $query->where('users.id', $user->id)])
            ->get();

        return Inertia::render('ProgramIndex', [
            'programs' => ProgramResource::collection($programs)->resolve(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();

        $program = Program::query()
            ->withCount(['users' => fn ($query) => $query->where('users.id', $user->id)])
            ->findOrFail($id);

        return Inertia::render('ProgramShow', [
            'program' => (new ProgramResource($program))->resolve(),
            'workouts' => Inertia::defer(fn () => WorkoutTemplateResource::collection(
                $program->workoutTemplates()->get()
            )->resolve()),
        ]);
    }

    public function enroll(Request $request, Program $program): RedirectResponse
    {
        $user = $request->user();

        $program->users()->syncWithoutDetaching([$user->id]);

        return redirect()->back();
    }
}
