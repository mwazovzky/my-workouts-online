<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TokenIssueRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    /**
     * Issue a new personal access token.
     */
    public function store(TokenIssueRequest $request): JsonResponse
    {
        $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = Auth::user()->createToken($request->input('device_name'));

        return response()->json(['token' => $token->plainTextToken], 201);
    }

    /**
     * Revoke the current token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(null, 204);
    }
}
