<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Resend\Laravel\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The package middleware is skipped when no secret is configured; this one
 * rejects every request instead.
 */
class VerifyResendWebhookSignature extends VerifyWebhookSignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (blank(config('resend.webhook.secret'))) {
            throw new AccessDeniedHttpException('Webhook secret is not configured.');
        }

        return parent::handle($request, $next);
    }
}
