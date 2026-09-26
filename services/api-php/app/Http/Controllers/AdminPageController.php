<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AdminPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Index');
    }

    public function equipment(): Response
    {
        return Inertia::render('Admin/EquipmentIndex');
    }

    public function createEquipment(): Response
    {
        return Inertia::render('Admin/EquipmentCreate');
    }

    public function editEquipment(int $id): Response
    {
        return Inertia::render('Admin/EquipmentEdit', ['id' => $id]);
    }

    public function categories(): Response
    {
        return Inertia::render('Admin/CategoryIndex');
    }

    public function createCategory(): Response
    {
        return Inertia::render('Admin/CategoryCreate');
    }

    public function editCategory(int $id): Response
    {
        return Inertia::render('Admin/CategoryEdit', ['id' => $id]);
    }

    public function exercises(): Response
    {
        return Inertia::render('Admin/ExerciseIndex');
    }

    public function createExercise(): Response
    {
        return Inertia::render('Admin/ExerciseCreate');
    }

    public function editExercise(int $id): Response
    {
        return Inertia::render('Admin/ExerciseEdit', ['id' => $id]);
    }

    public function workoutTemplates(): Response
    {
        return Inertia::render('Admin/WorkoutTemplateIndex');
    }

    public function createWorkoutTemplate(): Response
    {
        return Inertia::render('Admin/WorkoutTemplateCreate');
    }

    public function editWorkoutTemplate(int $id): Response
    {
        return Inertia::render('Admin/WorkoutTemplateEdit', ['id' => $id]);
    }

    public function programs(): Response
    {
        return Inertia::render('Admin/ProgramIndex');
    }

    public function createProgram(): Response
    {
        return Inertia::render('Admin/ProgramCreate');
    }

    public function editProgram(int $id): Response
    {
        return Inertia::render('Admin/ProgramEdit', ['id' => $id]);
    }
}
