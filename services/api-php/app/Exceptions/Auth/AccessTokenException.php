<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class AccessTokenException extends RuntimeException
{
    private function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function expired(): self
    {
        return new self('Access token expired.', 'token_expired');
    }

    public static function invalid(): self
    {
        return new self('Access token invalid.', 'unauthenticated');
    }
}
