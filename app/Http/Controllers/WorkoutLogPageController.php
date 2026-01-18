<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WorkoutLogPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WorkoutLogIndex');
    }

    public function show(int $id): Response
    {
        return Inertia::render('WorkoutLogShow', [
            'id' => $id,
        ]);
    }

    public function edit(int $id): Response
    {
        return Inertia::render('WorkoutLogEdit', [
            'id' => $id,
        ]);
    }
}
