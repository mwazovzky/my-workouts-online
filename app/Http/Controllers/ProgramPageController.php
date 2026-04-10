<?php

namespace App\Http\Controllers;

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
}
