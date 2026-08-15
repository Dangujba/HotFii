<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaystackWebhook;
use App\Models\PaymentWebhook;
use App\Services\Payments\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackService $paystack): Response
    {
        $body = $request->getContent();
        abort_unless($paystack->signatureIsValid($body, $request->header('x-paystack-signature')), 401);

        $payload = $request->json()->all();
        $eventType = (string) ($payload['event'] ?? 'unknown');
        $providerId = (string) ($payload['data']['id'] ?? hash('sha256', $body));
        $eventId = hash('sha256', $eventType.':'.$providerId);

        $webhook = PaymentWebhook::firstOrCreate(
            ['event_id' => $eventId],
            [
                'provider' => 'paystack',
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'received',
            ],
        );

        if (! $webhook->processed_at) {
            ProcessPaystackWebhook::dispatch($webhook);
        }

        return response('accepted', 202);
    }
}