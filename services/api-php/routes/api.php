<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\EquipmentController as AdminEquipmentController;
use App\Http\Controllers\Api\Admin\ExerciseController as AdminExerciseController;
use App\Http\Controllers\Api\Admin\ProgramController as AdminProgramController;
use App\Http\Controllers\Api\Admin\WorkoutTemplateController as AdminWorkoutTemplateController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\ReauthController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\WorkoutController;
use App\Http\Controllers\Api\WorkoutTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('meta', MetaController::class)->name('meta');

    Route::post('auth/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('auth/login', [SessionController::class, 'login'])->name('auth.login');
    Route::post('auth/refresh', [SessionController::class, 'refresh'])->name('auth.refresh');
    Route::post('auth/password/forgot', [PasswordResetController::class, 'forgot'])->middleware('throttle:6,1')->name('auth.password.forgot');
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('auth.password.reset');

    Route::middleware('auth:jwt')->group(function () {
        Route::get('me', MeController::class)->name('me');

        Route::post('auth/logout', [SessionController::class, 'logout'])->name('auth.logout');
        Route::post('auth/reauth', ReauthController::class)->middleware('throttle:6,1')->name('auth.reauth');
        Route::post('auth/email/verify', [EmailVerificationController::class, 'verify'])->middleware('throttle:6,1')->name('auth.email.verify');
        Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('auth.email.resend');

        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::patch('profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale');
        Route::patch('profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::middleware(['auth:jwt', 'verified'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);
        Route::post('programs/{program}/enroll', [ProgramController::class, 'enroll'])->name('programs.enroll');

        Route::get('workout-templates/{id}', [WorkoutTemplateController::class, 'show'])->whereNumber('id')->name('workout-templates.show');

        Route::get('exercises', [ExerciseController::class, 'index'])->name('exercises.index');

        Route::post('workouts/{workout}/complete', [WorkoutController::class, 'complete'])->name('workouts.complete');
        Route::post('workouts/{workout}/repeat', [WorkoutController::class, 'repeat'])->name('workouts.repeat');
        Route::patch('workouts/{workout}/save', [WorkoutController::class, 'save'])->name('workouts.save');
        Route::apiResource('workouts', WorkoutController::class)->except(['update']);

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::apiResource('equipment', AdminEquipmentController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::apiResource('categories', AdminCategoryController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::apiResource('exercises', AdminExerciseController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::apiResource('workout-templates', AdminWorkoutTemplateController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::apiResource('programs', AdminProgramController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
        });
    });
});
