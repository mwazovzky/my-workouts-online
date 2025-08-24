<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/programs', function () {
    return Inertia::render('ProgramIndex');
})->middleware(['auth', 'verified'])->name('programs.index');

Route::get('/programs/{id}', function ($id) {
    return Inertia::render('ProgramShow', ['id' => $id]);
})->middleware(['auth', 'verified'])->name('programs.show');

Route::get('/workout-templates/{id}', function ($id) {
    return Inertia::render('WorkoutTemplateShow', ['id' => $id]);
})->middleware(['auth', 'verified'])->name('workout.templates.show');

Route::get('/workout-logs', function () {
    return Inertia::render('WorkoutLogIndex');
})->middleware(['auth', 'verified'])->name('workout.logs.index');

Route::get('/workout-logs/{id}', function ($id) {
    return Inertia::render('WorkoutLogShow', ['id' => $id]);
})->middleware(['auth', 'verified'])->name('workout.logs.show');

Route::get('/workout-logs/{id}/edit', function ($id) {
    return Inertia::render('WorkoutLogEdit', ['id' => $id]);
})->middleware(['auth', 'verified'])->name('workout.logs.edit');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
