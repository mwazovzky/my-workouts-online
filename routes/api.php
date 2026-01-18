<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\WorkoutLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('programs')->name('api.programs.')->group(function () {
        Route::post('/{program}/enroll', [ProgramController::class, 'enroll'])->name('enroll');
    });

    Route::prefix('workout-logs')->name('api.workout.logs.')->group(function () {
        Route::post('/', [WorkoutLogController::class, 'store'])->name('store');
        Route::post('/{workout_log}/complete', [WorkoutLogController::class, 'complete'])->name('complete');
        Route::delete('/{workout_log}', [WorkoutLogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('activities')->name('api.activities.')->group(function () {
        Route::patch('/{activity}', [ActivityController::class, 'update'])->name('update');
    });
});
