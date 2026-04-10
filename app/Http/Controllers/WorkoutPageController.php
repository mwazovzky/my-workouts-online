<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WorkoutPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WorkoutIndex');
    }

    public function show(int $id): Response
    {
        return Inertia::render('WorkoutShow', ['id' => $id]);
    }

    public function edit(int $id): Response
    {
        return Inertia::render('WorkoutEdit', ['id' => $id]);
    }
}
