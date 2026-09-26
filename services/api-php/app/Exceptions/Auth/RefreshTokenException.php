<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class RefreshTokenException extends RuntimeException
{
    private function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function missing(): self
    {
        return new self('Refresh token missing.', 'refresh_token_missing');
    }

    public static function invalid(): self
    {
        return new self('Refresh token invalid.', 'refresh_token_invalid');
    }

    public static function expired(): self
    {
        return new self('Refresh token expired.', 'refresh_token_expired');
    }

    public static function reused(): self
    {
        return new self('Refresh token reuse detected.', 'refresh_token_reused');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], 401);
    }
}
