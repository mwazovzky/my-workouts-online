<?php

namespace App\Services\InboundEmail;

final readonly class InboundAttachment
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public string $content,
    ) {}
}
