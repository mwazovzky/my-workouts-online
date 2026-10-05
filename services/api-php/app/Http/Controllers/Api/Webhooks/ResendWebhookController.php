<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardInboundEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Resend webhooks (signature checked by VerifyResendWebhookSignature).
 * Only `email.received` is acted on; other event types are acknowledged.
 */
class ResendWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $emailId = $request->input('data.email_id');

        if ($request->input('type') === 'email.received' && is_string($emailId) && $emailId !== '') {
            ForwardInboundEmail::dispatch($emailId);
        }

        return response()->noContent();
    }
}
