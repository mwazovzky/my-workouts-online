<?php

namespace App\Http\Middleware;

use App\Http\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $requestId = RequestId::for($request);

        Log::shareContext([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'route_name' => $route?->getName(),
            'route_uri' => $route?->uri(),
        ]);

        $response = $next($request);
        $response->headers->set(RequestId::HEADER, $requestId);

        return $response;
    }
}
