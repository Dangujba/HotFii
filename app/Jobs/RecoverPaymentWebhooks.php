<?php

namespace App\Jobs;

use App\Models\PaymentWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecoverPaymentWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        PaymentWebhook::query()
            ->whereNull('processed_at')
            ->whereIn('status', ['received', 'failed'])
            ->where('attempts', '<', 5)
            ->where('updated_at', '<=', now()->subMinute())
            ->chunkById(100, fn ($webhooks) => $webhooks->each(
                fn ($webhook) => ProcessPaystackWebhook::dispatch($webhook),
            ));
    }
}