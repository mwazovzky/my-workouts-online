<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\TokenServiceInterface;
use Illuminate\Http\JsonResponse;

class JwksController extends Controller
{
    public function __invoke(TokenServiceInterface $tokens): JsonResponse
    {
        return response()->json($tokens->jwks())
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
