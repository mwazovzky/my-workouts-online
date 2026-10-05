<?php

namespace App\Services\InboundEmail;

/**
 * An email received on the domain, with attachment contents already downloaded.
 */
final readonly class InboundEmail
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $replyTo
     * @param  list<InboundAttachment>  $attachments
     */
    public function __construct(
        public string $id,
        public string $from,
        public array $to,
        public string $subject,
        public ?string $html,
        public ?string $text,
        public array $cc = [],
        public array $replyTo = [],
        public ?string $receivedAt = null,
        public array $attachments = [],
    ) {}
}
