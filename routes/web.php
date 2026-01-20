<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramPageController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WorkoutLogPageController;
use App\Http\Controllers\WorkoutTemplatePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index']);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/programs', [ProgramPageController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('programs.index');

Route::get('/programs/{id}', [ProgramPageController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('programs.show');

Route::post('/programs/{program}/enroll', [ProgramPageController::class, 'enroll'])
    ->middleware(['auth', 'verified'])
    ->name('programs.enroll');

Route::get('/workout-templates/{id}', [WorkoutTemplatePageController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('workout.templates.show');

Route::get('/workout-logs', [WorkoutLogPageController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('workout.logs.index');

Route::get('/workout-logs/{id}', [WorkoutLogPageController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('workout.logs.show');

Route::get('/workout-logs/{id}/edit', [WorkoutLogPageController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('workout.logs.edit');

Route::post('/workout-logs', [WorkoutLogPageController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('workout.logs.store');

Route::post('/workout-logs/{workoutLog}/complete', [WorkoutLogPageController::class, 'complete'])
    ->middleware(['auth', 'verified'])
    ->name('workout.logs.complete');

Route::delete('/workout-logs/{workoutLog}', [WorkoutLogPageController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('workout.logs.destroy');

Route::patch('/activities/{activity}', [ActivityController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('activities.update');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
