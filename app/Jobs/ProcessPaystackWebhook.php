<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\PaymentWebhook;
use App\Models\Transaction;
use App\Services\Billing\InvoiceSettlement;
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

    public function handle(PaymentProcessor $processor, InvoiceSettlement $settlement): void
    {
        $webhook = $this->webhook->refresh();
        if ($webhook->processed_at) {
            return;
        }

        $payload = $webhook->payload;
        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');

        try {
            if ($webhook->event_type === 'charge.success' && $invoice = $this->invoiceFor($data, $reference)) {
                // A platform invoice, not a guest sale. Verified against the
                // amount for the same reason markSuccessful() does: a charge
                // for less than the invoice must not clear it.
                if ((int) ($data['amount'] ?? 0) >= $invoice->total_kobo) {
                    $settlement->settle($invoice, 'paystack', $reference);
                }
            } elseif ($transaction = Transaction::where('reference', $reference)->first()) {
                if ($webhook->event_type === 'charge.success') {
                    $processor->markSuccessful($transaction, $data);
                } elseif (in_array($webhook->event_type, ['charge.failed', 'charge.abandoned'], true)) {
                    $processor->markFailed($transaction, $data);
                }
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

    /**
     * The invoice a charge settles, or null when the charge is an ordinary
     * access sale.
     *
     * The stored reference is checked first because it is ours and cannot be
     * spoofed; the metadata flag Paystack echoes back is the fallback for a
     * charge whose reference never made it onto the invoice.
     */
    private function invoiceFor(array $data, string $reference): ?Invoice
    {
        if ($reference !== '' && $invoice = Invoice::where('payment_reference', $reference)->first()) {
            return $invoice;
        }

        if (($data['metadata']['hotfii_purpose'] ?? null) !== 'invoice') {
            return null;
        }

        $uuid = $data['metadata']['invoice_uuid'] ?? null;

        return $uuid ? Invoice::where('uuid', $uuid)->first() : null;
    }
}