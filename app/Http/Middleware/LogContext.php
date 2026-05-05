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
        Log::shareContext([
            'request_id' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'url' => $request->url(),
        ]);

        return $next($request);
    }
}
