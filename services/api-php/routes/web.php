<?php

use App\Http\Controllers\Api\JwksController;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Non-API routes
|--------------------------------------------------------------------------
|
| Everything user-facing is the SPA in apps/web, served by nginx. Only the
| operational endpoints and the signing key set live outside /api.
|
*/

Route::get('/.well-known/jwks.json', JwksController::class)->name('jwks');

Route::get('/health', static fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]))->name('health');

Route::get('/health/ready', static function (TokenServiceInterface $tokens) {
    $components = [
        'database' => 'up',
        'jwt' => $tokens->hasKeys() ? 'up' : 'down',
    ];

    try {
        DB::connection()->getPdo();
    } catch (Throwable $e) {
        logger()->warning('Readiness check failed', [
            'component' => 'database',
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        $components['database'] = 'down';
    }

    if ($components['jwt'] === 'down') {
        logger()->warning('Readiness check failed', [
            'component' => 'jwt',
            'message' => 'JWT_PRIVATE_KEY / JWT_PUBLIC_KEY are not configured.',
        ]);
    }

    $degraded = in_array('down', $components, true);

    return response()->json([
        'status' => $degraded ? 'degraded' : 'ok',
        'components' => $components,
        'timestamp' => now()->toIso8601String(),
    ], $degraded ? 503 : 200);
})->name('health.ready');

/*
| Named for URL generation and signature validation only: the emailed
| verification link points at the SPA, which posts the parameters to the API.
| Reaching it here (php-fpm addressed directly) forwards to the SPA.
*/
Route::get('/verify-email/{id}/{hash}', static fn (int $id, string $hash) => redirect()->away(
    rtrim(config('app.frontend_url'), '/')."/verify-email/{$id}/{$hash}?".request()->getQueryString()
))->name('verification.verify');
