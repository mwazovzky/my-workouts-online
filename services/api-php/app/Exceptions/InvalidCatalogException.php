<?php

namespace App\Exceptions;

use Exception;

class InvalidCatalogException extends Exception
{
    /**
     * @param  array<int, string>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(sprintf(
            "The catalog data is invalid (%d violation(s)):\n- %s",
            count($violations),
            implode("\n- ", $violations),
        ));
    }
}
