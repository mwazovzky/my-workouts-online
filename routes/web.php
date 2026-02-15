<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramPageController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WorkoutPageController;
use App\Http\Controllers\WorkoutTemplatePageController;
use Illuminate\Support\Facades\DB;
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

Route::get('/workouts', [WorkoutPageController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.index');

Route::get('/workouts/{id}', [WorkoutPageController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.show');

Route::get('/workouts/{id}/edit', [WorkoutPageController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.edit');

Route::post('/workouts', [WorkoutPageController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.store');

Route::patch('/workouts/{workout}/save', [WorkoutPageController::class, 'save'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.save');

Route::post('/workouts/{workout}/complete', [WorkoutPageController::class, 'complete'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.complete');

Route::post('/workouts/{workout}/repeat', [WorkoutPageController::class, 'repeat'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.repeat');

Route::delete('/workouts/{workout}', [WorkoutPageController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('workouts.destroy');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/health', static fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('health');

Route::get('/health/ready', static function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'components' => [
                'database' => 'up',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (Throwable) {
        return response()->json([
            'status' => 'degraded',
            'components' => [
                'database' => 'down',
            ],
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
})->name('health.ready');

require __DIR__.'/auth.php';
