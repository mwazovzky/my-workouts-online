<?php

namespace App\Providers;

use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Workout\WorkoutServiceInterface::class,
            \App\Services\Workout\WorkoutService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Relation::morphMap([
            'workout_template' => \App\Models\WorkoutTemplate::class,
            'workout' => \App\Models\Workout::class,
        ]);

        // Share authenticated user info with Inertia pages
        Inertia::share('auth.user', function (Request $request) {
            $user = $request->user();
            if (! $user) {
                return null;
            }

            return (new UserResource($user))->resolve();
        });
    }
}
