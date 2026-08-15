<?php

namespace App\Jobs;

use App\Models\PaymentWebhook;
use App\Models\Transaction;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessPaystackWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;

    public function __construct(public readonly PaymentWebhook $webhook)
    {
        $this->onQueue('payments');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('payment-webhook-'.$this->webhook->id))->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [5, 20, 60, 180, 600];
    }

    public function tags(): array
    {
        return ['payment-webhook:'.$this->webhook->id];
    }

    public function handle(PaymentProcessor $processor): void
    {
        $webhook = $this->webhook->refresh();
        if ($webhook->processed_at) {
            return;
        }

        $payload = $webhook->payload;
        $data = $payload['data'] ?? [];
        $transaction = Transaction::where('reference', $data['reference'] ?? '')->first();

        try {
            if ($transaction && $webhook->event_type === 'charge.success') {
                $processor->markSuccessful($transaction, $data);
            } elseif ($transaction && in_array($webhook->event_type, ['charge.failed', 'charge.abandoned'], true)) {
                $processor->markFailed($transaction, $data);
            }

            $webhook->update([
                'status' => 'processed',
                'processed_at' => now(),
                'attempts' => $webhook->attempts + 1,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $webhook->update([
                'status' => 'failed',
                'attempts' => $webhook->attempts + 1,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            throw $exception;
        }
    }
}