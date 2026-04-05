<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\WorkoutController;
use App\Http\Controllers\Api\WorkoutTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('tokens', [TokenController::class, 'store'])->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);
        Route::post('programs/{program}/enroll', [ProgramController::class, 'enroll'])->name('programs.enroll');

        Route::get('workout-templates/{id}', [WorkoutTemplateController::class, 'show'])->name('workout-templates.show');

        Route::post('workouts/{workout}/complete', [WorkoutController::class, 'complete'])->name('workouts.complete');
        Route::post('workouts/{workout}/repeat', [WorkoutController::class, 'repeat'])->name('workouts.repeat');
        Route::patch('workouts/{workout}/save', [WorkoutController::class, 'save'])->name('workouts.save');
        Route::apiResource('workouts', WorkoutController::class)->except(['update']);
    });
});
