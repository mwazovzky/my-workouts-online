<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\QueryBuilders\ProgramQueryBuilder;
use Inertia\Inertia;
use Inertia\Response;

class ProgramPageController extends Controller
{
    public function index(ProgramQueryBuilder $query): Response
    {
        $programs = $query
            ->with('users')
            ->get();

        return Inertia::render('ProgramIndex', [
            'programs' => $programs,
        ]);
    }

    public function show(int $id): Response
    {
        $program = Program::query()
            ->with(['users', 'workoutTemplates'])
            ->findOrFail($id);

        return Inertia::render('ProgramShow', [
            'program' => $program,
        ]);
    }
}
