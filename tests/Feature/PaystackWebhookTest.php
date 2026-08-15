<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaystackWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_duplicate_webhooks_are_persisted_once_and_queued(): void
    {
        Queue::fake();
        config(['services.paystack.secret' => 'sk_test_secret']);
        $payload = ['event' => 'charge.success', 'data' => ['id' => 123, 'reference' => 'HF-TEST', 'amount' => 50000]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $body, 'sk_test_secret');

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $body)->assertAccepted();

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $body)->assertAccepted();

        $this->assertDatabaseCount('payment_webhooks', 1);
        Queue::assertPushed(ProcessPaystackWebhook::class, 2);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.paystack.secret' => 'sk_test_secret']);
        $this->withHeader('X-Paystack-Signature', 'invalid')
            ->postJson(route('webhooks.paystack'), ['event' => 'charge.success'])
            ->assertUnauthorized();
    }
}