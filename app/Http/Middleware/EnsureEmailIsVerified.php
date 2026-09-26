<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseEnsureEmailIsVerified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same as the framework middleware, but JSON clients get a coded 403 so they
 * can route to their verification screen.
 */
class EnsureEmailIsVerified extends BaseEnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        $user = $request->user();

        if ($request->expectsJson() && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'code' => 'email_unverified',
            ], 403);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
