<?php

namespace App\Exceptions\Api;

use App\Enums\ErrorCode;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An HTTP error with an explicit machine-readable code for the API Problem.
 */
class ApiException extends HttpException
{
    /**
     * @param  array<string, string>  $headers  extra response headers, e.g. `Retry-After`
     */
    public function __construct(int $status, public readonly ErrorCode $errorCode, string $message = '', array $headers = [])
    {
        parent::__construct($status, $message, headers: $headers);
    }
}
