<?php

declare(strict_types=1);

namespace Tests\Feature\InboundEmail;

use App\Jobs\ForwardInboundEmail;
use App\Mail\ForwardedInboundEmail;
use App\Services\InboundEmail\InboundAttachment;
use App\Services\InboundEmail\InboundEmail;
use App\Services\InboundEmail\InboundEmailSourceInterface;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ForwardInboundEmailTest extends TestCase
{
    private FakeInboundEmailSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.name' => 'My Workouts',
            'mail.from.address' => 'noreply@my-workouts.online',
            'services.inbound_email.forward_to' => 'owner@example.com',
        ]);

        $this->source = new FakeInboundEmailSource;
        $this->app->instance(InboundEmailSourceInterface::class, $this->source);

        Mail::fake();
    }

    #[Test]
    public function it_forwards_the_email_to_the_support_inbox(): void
    {
        $this->source->emails['email-1'] = $this->email(html: '<p>My workout is gone</p>');

        ForwardInboundEmail::dispatchSync('email-1');

        Mail::assertSent(ForwardedInboundEmail::class, function (ForwardedInboundEmail $mail): bool {
            return $mail->hasTo('owner@example.com')
                && $mail->hasFrom('noreply@my-workouts.online', 'Jane Doe via My Workouts')
                && $mail->hasReplyTo('jane@example.com', 'Jane Doe')
                && $mail->hasSubject('Lost workout');
        });
    }

    #[Test]
    public function it_includes_the_original_headers_and_html_body(): void
    {
        $mail = new ForwardedInboundEmail($this->email(html: '<p>My workout is gone</p>'));

        $mail->assertSeeInHtml('<p>My workout is gone</p>', false);
        $mail->assertSeeInHtml('Jane Doe &lt;jane@example.com&gt;', false);
        $mail->assertSeeInHtml('support@my-workouts.online');
    }

    #[Test]
    public function it_escapes_a_plain_text_body(): void
    {
        $mail = new ForwardedInboundEmail($this->email(html: null, text: "Hi <script>\nthanks"));

        $mail->assertSeeInHtml('Hi &lt;script&gt;', false);
        $mail->assertDontSeeInHtml('<script>', false);
    }

    #[Test]
    public function it_prefers_the_original_reply_to_address(): void
    {
        $mail = new ForwardedInboundEmail($this->email(replyTo: ['team@example.com']));

        $this->assertTrue($mail->hasReplyTo('team@example.com'));
        $this->assertFalse($mail->hasReplyTo('jane@example.com'));
    }

    #[Test]
    public function it_uses_the_bare_address_when_the_sender_has_no_name(): void
    {
        $mail = new ForwardedInboundEmail($this->email(from: 'jane@example.com', subject: ''));

        $this->assertTrue($mail->hasFrom('noreply@my-workouts.online', 'jane@example.com via My Workouts'));
        $this->assertTrue($mail->hasSubject('(no subject)'));
    }

    #[Test]
    public function it_attaches_the_original_attachments(): void
    {
        $mail = new ForwardedInboundEmail($this->email(attachments: [
            new InboundAttachment('screenshot.png', 'image/png', 'png-bytes'),
        ]));

        $mail->assertHasAttachedData('png-bytes', 'screenshot.png', ['mime' => 'image/png']);
    }

    #[Test]
    public function it_forwards_an_email_only_once(): void
    {
        $this->source->emails['email-1'] = $this->email();

        ForwardInboundEmail::dispatchSync('email-1');
        ForwardInboundEmail::dispatchSync('email-1');

        Mail::assertSentCount(1);
        $this->assertSame(1, $this->source->lookups);
    }

    #[Test]
    public function it_does_nothing_when_no_forwarding_address_is_configured(): void
    {
        config(['services.inbound_email.forward_to' => null]);
        $this->source->emails['email-1'] = $this->email();

        ForwardInboundEmail::dispatchSync('email-1');

        Mail::assertNothingSent();
        $this->assertSame(0, $this->source->lookups);
    }

    /**
     * @param  list<string>  $replyTo
     * @param  list<InboundAttachment>  $attachments
     */
    private function email(
        string $from = 'Jane Doe <jane@example.com>',
        string $subject = 'Lost workout',
        ?string $html = '<p>Hello</p>',
        ?string $text = 'Hello',
        array $replyTo = [],
        array $attachments = [],
    ): InboundEmail {
        return new InboundEmail(
            id: 'email-1',
            from: $from,
            to: ['support@my-workouts.online'],
            subject: $subject,
            html: $html,
            text: $text,
            replyTo: $replyTo,
            receivedAt: '2026-10-05T20:00:00.000Z',
            attachments: $attachments,
        );
    }
}
