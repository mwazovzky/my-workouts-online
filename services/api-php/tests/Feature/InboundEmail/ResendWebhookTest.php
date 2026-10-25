<?php

declare(strict_types=1);

namespace Tests\Feature\InboundEmail;

use App\Jobs\ForwardInboundEmail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResendWebhookTest extends TestCase
{
    private const SECRET = 'whsec_dGVzdC13ZWJob29rLXNlY3JldC0xMjM0NTY3OA==';

    protected function setUp(): void
    {
        parent::setUp();

        config(['resend.webhook.secret' => self::SECRET]);
        Queue::fake();
    }

    #[Test]
    public function it_queues_forwarding_for_a_received_email(): void
    {
        $this->postSigned($this->receivedPayload('email-123'))->assertNoContent();

        Queue::assertPushed(ForwardInboundEmail::class, fn (ForwardInboundEmail $job) => $job->emailId === 'email-123');
    }

    #[Test]
    public function it_acknowledges_other_event_types_without_forwarding(): void
    {
        $this->postSigned(['type' => 'email.delivered', 'data' => ['email_id' => 'email-123']])->assertNoContent();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_ignores_a_received_event_without_an_email_id(): void
    {
        $this->postSigned(['type' => 'email.received', 'data' => []])->assertNoContent();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_rejects_an_invalid_signature(): void
    {
        $body = json_encode($this->receivedPayload('email-123'), JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/resend', server: $this->serverHeaders([
            'svix-id' => 'msg_1',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,'.base64_encode('forged'),
        ]), content: $body)->assertForbidden();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_rejects_unsigned_requests(): void
    {
        $this->postJson('/api/webhooks/resend', $this->receivedPayload('email-123'))->assertForbidden();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_rejects_a_stale_timestamp(): void
    {
        $this->postSigned($this->receivedPayload('email-123'), time() - 3600)->assertForbidden();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_rejects_every_request_when_no_secret_is_configured(): void
    {
        config(['resend.webhook.secret' => null]);

        $this->postSigned($this->receivedPayload('email-123'))->assertForbidden();

        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, mixed>
     */
    private function receivedPayload(string $emailId): array
    {
        return [
            'type' => 'email.received',
            'created_at' => '2026-10-05T20:00:00.000Z',
            'data' => [
                'email_id' => $emailId,
                'from' => 'Jane <jane@example.com>',
                'to' => ['support@my-workouts.online'],
                'subject' => 'Help',
                'attachments' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postSigned(array $payload, ?int $timestamp = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $key = base64_decode(substr(self::SECRET, strlen('whsec_')));
        $signature = base64_encode(hash_hmac('sha256', "msg_1.{$timestamp}.{$body}", $key, true));

        return $this->call('POST', '/api/webhooks/resend', server: $this->serverHeaders([
            'svix-id' => 'msg_1',
            'svix-timestamp' => (string) $timestamp,
            'svix-signature' => 'v1,'.$signature,
        ]), content: $body);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function serverHeaders(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }
}
