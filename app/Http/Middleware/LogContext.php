<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        Log::shareContext([
            'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'route_name' => $route?->getName(),
            'route_uri' => $route?->uri(),
        ]);

        return $next($request);
    }
}
