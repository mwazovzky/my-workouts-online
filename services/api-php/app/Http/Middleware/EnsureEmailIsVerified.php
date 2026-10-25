<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\Api\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-only variant of the framework middleware: unverified users get a coded
 * 403 so clients can route to their verification screen.
 */
class EnsureEmailIsVerified
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return ApiErrorResponse::problem(403, ErrorCode::EmailUnverified, 'Your email address is not verified.');
        }

        return $next($request);
    }
}
