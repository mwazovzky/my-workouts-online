<?php

namespace App\Mail;

use App\Services\InboundEmail\InboundAttachment;
use App\Services\InboundEmail\InboundEmail;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Address as MimeAddress;

/**
 * A received email relayed to the support inbox. It is sent from the app's own
 * address (the original sender's domain would fail SPF/DMARC) and replies go
 * back to the original sender.
 */
class ForwardedInboundEmail extends Mailable
{
    public function __construct(public InboundEmail $email) {}

    public function envelope(): Envelope
    {
        $sender = MimeAddress::create($this->email->from);

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                ($sender->getName() ?: $sender->getAddress()).' via '.config('app.name'),
            ),
            replyTo: array_map(
                fn (string $address): Address => $this->toAddress($address),
                $this->email->replyTo ?: [$this->email->from],
            ),
            subject: $this->email->subject !== '' ? $this->email->subject : '(no subject)',
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->summary().$this->body());
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (InboundAttachment $attachment): Attachment => Attachment::fromData(
                fn (): string => $attachment->content,
                $attachment->filename,
            )->withMime($attachment->contentType),
            $this->email->attachments,
        );
    }

    private function summary(): string
    {
        $rows = array_filter([
            'From' => $this->email->from,
            'To' => implode(', ', $this->email->to),
            'Cc' => implode(', ', $this->email->cc),
            'Date' => $this->email->receivedAt,
        ]);

        $lines = array_map(
            fn (string $label, string $value): string => '<strong>'.$label.':</strong> '.e($value),
            array_keys($rows),
            $rows,
        );

        return '<div style="border-bottom:1px solid #ccc;color:#555;font:13px sans-serif;margin-bottom:16px;padding-bottom:8px">'
            .implode('<br>', $lines)
            .'</div>';
    }

    private function body(): string
    {
        if (filled($this->email->html)) {
            return $this->email->html;
        }

        return '<div style="white-space:pre-wrap">'.e($this->email->text ?? '').'</div>';
    }

    private function toAddress(string $address): Address
    {
        $parsed = MimeAddress::create($address);

        return new Address($parsed->getAddress(), $parsed->getName() ?: null);
    }
}
