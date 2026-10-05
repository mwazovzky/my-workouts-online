<?php

namespace App\Services\InboundEmail;

interface InboundEmailSourceInterface
{
    /**
     * Fetch a received email, including its body and attachment contents.
     */
    public function find(string $id): InboundEmail;
}
