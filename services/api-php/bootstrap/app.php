<?php

use App\Auth\JwtUserResolver;
use App\Enums\ErrorCode;
use App\Exceptions\Api\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(\App\Http\Middleware\LogContext::class)
                ->group(__DIR__.'/../routes/web.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);

        // Every /api route: API_RATE_LIMIT_PER_MINUTE per client IP (the `api` limiter).
        $middleware->throttleApi();

        // The locale comes first, so the throttle's 429 and route binding's
        // 404 are localized too.
        $middleware->api(prepend: [
            \App\Http\Middleware\NegotiateApiLocale::class,
        ], append: [
            \App\Http\Middleware\LogContext::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(fn (Throwable $e) => array_filter([
            'exception_class' => $e::class,
            'exception_file' => config('app.debug') ? $e->getFile().':'.$e->getLine() : null,
        ]));

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                $code = ErrorCode::tryFrom((string) $request->attributes->get(JwtUserResolver::ERROR_ATTRIBUTE, ''))
                    ?? ErrorCode::Unauthenticated;

                return ApiErrorResponse::problem(401, $code, $e->getMessage());
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::fromValidation($e);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::fromHttpException($e);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::fromThrowable($e);
            }
        });
    })->create();
