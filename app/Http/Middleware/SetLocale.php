<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @var list<string>
     */
    private array $availableLocales;

    public function __construct()
    {
        $this->availableLocales = array_keys(config('app.available_locales', []));
    }

    /**
     * Set the application locale based on the authenticated user's preference.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->locale) {
            App::setLocale($user->locale);

            return $next($request);
        }

        $sessionLocale = $request->session()->get('locale');

        if (is_string($sessionLocale) && in_array($sessionLocale, $this->availableLocales, true)) {
            App::setLocale($sessionLocale);

            return $next($request);
        }

        $preferredLocale = $request->getPreferredLanguage($this->availableLocales);

        if (is_string($preferredLocale)) {
            App::setLocale($preferredLocale);
        }

        return $next($request);
    }
}
