<?php

namespace App\Providers;

use App\Auth\EmailVerificationLink;
use App\Auth\JwtGuard;
use App\Auth\JwtUserResolver;
use App\Services\Auth\TokenService;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            TokenServiceInterface::class,
            fn ($app) => new TokenService($app['config']->get('jwt'))
        );

        $this->app->bind(
            \App\Services\Workout\WorkoutServiceInterface::class,
            \App\Services\Workout\WorkoutService::class
        );

        $this->app->bind(
            \App\Services\Admin\EquipmentServiceInterface::class,
            \App\Services\Admin\EquipmentService::class
        );

        $this->app->bind(
            \App\Services\Admin\CategoryServiceInterface::class,
            \App\Services\Admin\CategoryService::class
        );

        $this->app->bind(
            \App\Services\Admin\ExerciseServiceInterface::class,
            \App\Services\Admin\ExerciseService::class
        );

        $this->app->bind(
            \App\Services\Admin\WorkoutTemplateServiceInterface::class,
            \App\Services\Admin\WorkoutTemplateService::class
        );

        $this->app->bind(
            \App\Services\Admin\ProgramServiceInterface::class,
            \App\Services\Admin\ProgramService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('jwt', function ($app, string $name, array $config): JwtGuard {
            $guard = new JwtGuard(
                $app->make(JwtUserResolver::class),
                $app['request'],
                Auth::createUserProvider($config['provider'] ?? null),
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });

        VerifyEmail::createUrlUsing(fn ($notifiable) => EmailVerificationLink::forUser($notifiable));

        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => sprintf(
            '%s/reset-password/%s?email=%s',
            rtrim(config('app.frontend_url'), '/'),
            $token,
            urlencode($notifiable->getEmailForPasswordReset()),
        ));

        Relation::morphMap([
            'workout_template' => \App\Models\WorkoutTemplate::class,
            'workout' => \App\Models\Workout::class,
            'exercise' => \App\Models\Exercise::class,
            'category' => \App\Models\Category::class,
            'equipment' => \App\Models\Equipment::class,
            'program' => \App\Models\Program::class,
        ]);
    }
}
