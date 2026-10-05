<?php

namespace App\Jobs;

use App\Mail\ForwardedInboundEmail;
use App\Services\InboundEmail\InboundEmailSourceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Relays a received email to the configured support inbox. Webhooks can be
 * delivered more than once, so a forwarded id is remembered for a week.
 */
class ForwardInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $emailId) {}

    public function handle(InboundEmailSourceInterface $source): void
    {
        $forwardTo = config('mail.inbound.forward_to');

        if (blank($forwardTo)) {
            Log::warning('Inbound email not forwarded: MAIL_INBOUND_FORWARD_TO is not set.', ['email_id' => $this->emailId]);

            return;
        }

        if (Cache::has($this->cacheKey())) {
            return;
        }

        Mail::to($forwardTo)->send(new ForwardedInboundEmail($source->find($this->emailId)));

        Cache::put($this->cacheKey(), true, now()->addWeek());
    }

    private function cacheKey(): string
    {
        return 'inbound-email-forwarded:'.$this->emailId;
    }
}
