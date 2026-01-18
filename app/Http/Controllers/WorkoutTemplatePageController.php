<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WorkoutTemplatePageController extends Controller
{
    public function show(int $id): Response
    {
        return Inertia::render('WorkoutTemplateShow', [
            'id' => $id,
        ]);
    }
}
