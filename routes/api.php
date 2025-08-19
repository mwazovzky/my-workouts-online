<?php

use App\Http\Controllers\Api\ProgramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('programs', [ProgramController::class, 'index'])->name('api.programs.index');
    Route::get('programs/{id}', [ProgramController::class, 'show'])->name('api.programs.show');
});
