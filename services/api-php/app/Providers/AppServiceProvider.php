<?php

namespace App\Providers;

use App\Auth\EmailVerificationLink;
use App\Auth\JwtGuard;
use App\Auth\JwtUserResolver;
use App\Services\Auth\TokenService;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        $this->app->bind(
            \App\Services\Catalog\CatalogLoader::class,
            fn ($app) => new \App\Services\Catalog\CatalogLoader($app['config']->get('catalog.path'))
        );

        $this->app->bind(
            \App\Services\Catalog\CatalogValidator::class,
            fn ($app) => new \App\Services\Catalog\CatalogValidator(
                array_keys($app['config']->get('app.available_locales'))
            )
        );

        $this->app->bind(
            \App\Services\InboundEmail\InboundEmailSourceInterface::class,
            \App\Services\InboundEmail\ResendInboundEmailSource::class
        );

        $this->app->bind(
            \App\Services\Catalog\CatalogSyncServiceInterface::class,
            \App\Services\Catalog\CatalogSyncService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        foreach (['program', 'workout', 'workoutTemplate', 'equipment', 'category', 'exercise', 'workout_template'] as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('app.api_rate_limit'))
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

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
