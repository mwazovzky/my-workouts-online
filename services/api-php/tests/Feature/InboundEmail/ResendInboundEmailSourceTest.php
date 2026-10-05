<?php

declare(strict_types=1);

namespace Tests\Feature\InboundEmail;

use App\Services\InboundEmail\ResendInboundEmailSource;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Resend\Client;
use Resend\Contracts\Transporter;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Resend\ValueObjects\Transporter\Payload;
use Tests\TestCase;

final class ResendInboundEmailSourceTest extends TestCase
{
    #[Test]
    public function it_maps_a_received_email_and_downloads_its_attachments(): void
    {
        Http::fake(['files.resend.test/*' => Http::response('png-bytes')]);

        $source = new ResendInboundEmailSource($this->client([
            '/emails/receiving/email-1' => [
                'object' => 'email',
                'id' => 'email-1',
                'from' => 'Jane Doe <jane@example.com>',
                'to' => ['support@my-workouts.online'],
                'cc' => [],
                'reply_to' => ['team@example.com'],
                'created_at' => '2026-10-05 20:00:00',
                'subject' => 'Lost workout',
                'html' => '<p>Hi</p>',
                'text' => 'Hi',
                'attachments' => [['id' => 'att-1', 'filename' => 'screenshot.png']],
            ],
            '/emails/receiving/email-1/attachments' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'att-1',
                    'filename' => 'screenshot.png',
                    'content_type' => 'image/png',
                    'download_url' => 'https://files.resend.test/att-1',
                ]],
            ],
        ]));

        $email = $source->find('email-1');

        $this->assertSame('Jane Doe <jane@example.com>', $email->from);
        $this->assertSame(['support@my-workouts.online'], $email->to);
        $this->assertSame(['team@example.com'], $email->replyTo);
        $this->assertSame('Lost workout', $email->subject);
        $this->assertSame('<p>Hi</p>', $email->html);
        $this->assertCount(1, $email->attachments);
        $this->assertSame('screenshot.png', $email->attachments[0]->filename);
        $this->assertSame('image/png', $email->attachments[0]->contentType);
        $this->assertSame('png-bytes', $email->attachments[0]->content);
    }

    #[Test]
    public function it_skips_the_attachment_lookup_when_there_are_none(): void
    {
        Http::fake();

        $source = new ResendInboundEmailSource($this->client([
            '/emails/receiving/email-2' => [
                'object' => 'email',
                'id' => 'email-2',
                'from' => 'jane@example.com',
                'to' => ['support@my-workouts.online'],
                'subject' => null,
                'html' => null,
                'text' => 'Hi',
                'attachments' => [],
            ],
        ]));

        $email = $source->find('email-2');

        $this->assertSame('', $email->subject);
        $this->assertSame([], $email->replyTo);
        $this->assertSame([], $email->attachments);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses  keyed by request path
     */
    private function client(array $responses): Client
    {
        return new Client(new class($responses) implements Transporter
        {
            /**
             * @param  array<string, array<string, mixed>>  $responses
             */
            public function __construct(private array $responses) {}

            public function request(Payload $payload): array
            {
                $path = $payload->toRequest(BaseUri::from('api.resend.test'), new Headers([]))->getUri()->getPath();

                return $this->responses[$path] ?? throw new \RuntimeException("Unexpected Resend request: {$path}");
            }
        });
    }
}
