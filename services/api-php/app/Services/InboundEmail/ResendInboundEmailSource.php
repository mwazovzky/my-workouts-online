<?php

namespace App\Services\InboundEmail;

use Illuminate\Support\Facades\Http;
use Resend\Contracts\Client;

/**
 * Reads received emails from the Resend receiving API. The webhook only carries
 * metadata, so the body and attachments are fetched here.
 */
class ResendInboundEmailSource implements InboundEmailSourceInterface
{
    public function __construct(private Client $resend) {}

    public function find(string $id): InboundEmail
    {
        $email = $this->resend->emails->receiving->get($id);

        return new InboundEmail(
            id: $email->id,
            from: $email->from,
            to: array_values($email->to ?? []),
            subject: $email->subject ?? '',
            html: $email->html,
            text: $email->text,
            cc: array_values($email->cc ?? []),
            replyTo: array_values($email->reply_to ?? []),
            receivedAt: $email->created_at,
            attachments: ($email->attachments ?? []) === [] ? [] : $this->attachments($id),
        );
    }

    /**
     * @return list<InboundAttachment>
     */
    private function attachments(string $emailId): array
    {
        $attachments = [];

        foreach ($this->resend->emails->receiving->attachments->list($emailId) as $attachment) {
            $attachments[] = new InboundAttachment(
                filename: $attachment->filename,
                contentType: $attachment->content_type,
                content: Http::timeout(30)->get($attachment->download_url)->throw()->body(),
            );
        }

        return $attachments;
    }
}
