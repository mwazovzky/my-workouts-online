<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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

        if (! $user || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'code' => 'email_unverified',
            ], 403);
        }

        return $next($request);
    }
}
