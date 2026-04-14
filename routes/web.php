<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramPageController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WorkoutPageController;
use App\Http\Controllers\WorkoutTemplatePageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index']);
Route::patch('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/programs', [ProgramPageController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('programs.index');

Route::get('/programs/{id}', [ProgramPageController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('programs.show');

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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
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
