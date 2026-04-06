<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ProgramIndex');
    }

    public function show(int $id): Response
    {
        return Inertia::render('ProgramShow', ['id' => $id]);
    }

    public function enroll(Request $request, Program $program): RedirectResponse
    {
        $user = $request->user();

        $program->users()->syncWithoutDetaching([$user->id]);

        return redirect()->back();
    }
}
