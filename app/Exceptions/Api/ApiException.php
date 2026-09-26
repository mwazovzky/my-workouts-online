<?php

namespace App\Exceptions\Api;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An HTTP error with an explicit machine-readable code for the API envelope.
 */
class ApiException extends HttpException
{
    public function __construct(int $status, public readonly string $errorCode, string $message = '')
    {
        parent::__construct($status, $message);
    }
}
