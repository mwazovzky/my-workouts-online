<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public application options every client needs before sign-in.
 */
class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'available_locales' => config('app.available_locales', []),
                'theme_preferences' => ['light', 'dark', 'system'],
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
