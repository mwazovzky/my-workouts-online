<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * API locale comes only from `Accept-Language`: the client decides what it
 * renders, the stored profile locale is just a synced preference. Responses
 * declare the locale used so caches split on the header.
 */
class NegotiateApiLocale
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('app.available_locales', []));
        $fallback = config('app.fallback_locale', 'en');

        $locale = $request->getPreferredLanguage($available);

        if (! is_string($locale) || ! in_array($locale, $available, true)) {
            $locale = $fallback;
        }

        App::setLocale($locale);

        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary', ''))));
        $vary[] = 'Accept-Language';
        $response->headers->set('Vary', implode(', ', array_unique($vary)));

        return $response;
    }
}
