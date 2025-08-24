<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\WorkoutLogController;
use App\Http\Controllers\Api\WorkoutTemplateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('programs')->name('api.programs.')->group(function () {
        Route::get('/', [ProgramController::class, 'index'])->name('index');
        Route::get('/{program}', [ProgramController::class, 'show'])->name('show');
    });

    Route::prefix('workout-templates')->name('api.workout.templates.')->group(function () {
        Route::get('/{workout_template}', [WorkoutTemplateController::class, 'show'])->name('show');
    });

    Route::prefix('workout-logs')->name('api.workout.logs.')->group(function () {
        Route::get('/', [WorkoutLogController::class, 'index'])->name('index');
        Route::post('/', [WorkoutLogController::class, 'store'])->name('store');
        Route::get('/{workout_log}', [WorkoutLogController::class, 'show'])->name('show');
        Route::post('/{workout_log}/complete', [WorkoutLogController::class, 'complete'])->name('complete');
        Route::delete('/{workout_log}', [WorkoutLogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('activities')->name('api.activities.')->group(function () {
        Route::patch('/{activity}', [ActivityController::class, 'update'])->name('update');
    });
});
