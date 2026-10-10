<?php

namespace App\Exceptions\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RefreshTokenException extends RuntimeException
{
    private function __construct(string $message, public readonly ErrorCode $errorCode)
    {
        parent::__construct($message);
    }

    public static function missing(): self
    {
        return new self('Refresh token missing.', ErrorCode::RefreshTokenMissing);
    }

    public static function invalid(): self
    {
        return new self('Refresh token invalid.', ErrorCode::RefreshTokenInvalid);
    }

    public static function expired(): self
    {
        return new self('Refresh token expired.', ErrorCode::RefreshTokenExpired);
    }

    public static function reused(): self
    {
        return new self('Refresh token reuse detected.', ErrorCode::RefreshTokenReused);
    }

    public function render(): JsonResponse
    {
        return ApiErrorResponse::problem(401, $this->errorCode, $this->getMessage());
    }
}
