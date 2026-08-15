<?php

namespace App\Services\Payments;

use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    public function initialize(Transaction $transaction, Organization $organization, string $email, string $callbackUrl): array
    {
        $payload = [
            'email' => $email,
            'amount' => $transaction->gross_amount_kobo,
            'reference' => $transaction->reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'organization_uuid' => $organization->uuid,
                'transaction_uuid' => $transaction->uuid,
            ],
        ];

        if ($organization->paystack_subaccount_code) {
            $payload['subaccount'] = $organization->paystack_subaccount_code;
            $payload['transaction_charge'] = $transaction->platform_fee_kobo;
            $payload['bearer'] = 'subaccount';
        }

        $response = Http::withToken((string) config('services.paystack.secret'))
            ->acceptJson()
            ->post(rtrim((string) config('services.paystack.url'), '/').'/transaction/initialize', $payload);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message', 'Paystack could not initialize this payment.'));
        }

        return $response->json('data');
    }

    public function verify(string $reference): array
    {
        $response = Http::withToken((string) config('services.paystack.secret'))
            ->acceptJson()
            ->get(rtrim((string) config('services.paystack.url'), '/').'/transaction/verify/'.urlencode($reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message', 'Paystack could not verify this payment.'));
        }

        return $response->json('data');
    }

    public function signatureIsValid(string $body, ?string $signature): bool
    {
        if (! $signature || ! config('services.paystack.secret')) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $body, (string) config('services.paystack.secret')), $signature);
    }

    public function isLiveMode(): bool
    {
        return str_starts_with((string) config('services.paystack.secret'), 'sk_live_');
    }
}