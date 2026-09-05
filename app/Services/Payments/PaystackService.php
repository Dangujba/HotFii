<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    public function configured(): bool
    {
        return filled(config('services.paystack.secret'));
    }

    /**
     * Nigerian banks Paystack can settle to, cached for a day.
     * Returns [] when Paystack is unreachable so callers can degrade
     * to a free-text bank name and manual review.
     *
     * @return array<int, array{name: string, code: string, slug: string}>
     */
    public function banks(): array
    {
        if (! $this->configured()) {
            return [];
        }

        $key = 'paystack.banks.'.($this->isLiveMode() ? 'live' : 'test');
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            // The settings page waits on this, so keep it short and do not
            // retry — an empty list degrades to manual review.
            $response = Http::withToken((string) config('services.paystack.secret'))
                ->acceptJson()
                ->timeout(8)
                ->get($this->endpoint('/bank'), [
                    'currency' => 'NGN',
                    'country' => 'nigeria',
                    'perPage' => 200,
                ]);
        } catch (\Throwable) {
            Cache::put($key, [], now()->addMinutes(5));

            return [];
        }

        if (! $response->successful() || ! $response->json('status')) {
            Cache::put($key, [], now()->addMinutes(5));

            return [];
        }

        $banks = collect($response->json('data') ?? [])
            ->filter(fn (array $bank): bool => filled($bank['code'] ?? null) && filled($bank['name'] ?? null))
            ->map(fn (array $bank): array => [
                'name' => (string) $bank['name'],
                'code' => (string) $bank['code'],
                'slug' => (string) ($bank['slug'] ?? ''),
            ])
            ->unique('code')
            ->sortBy('name')
            ->values()
            ->all();

        if ($banks !== []) {
            Cache::put($key, $banks, now()->addDay());
        } else {
            Cache::put($key, [], now()->addMinutes(5));
        }

        return $banks;
    }

    public function bankName(string $bankCode): ?string
    {
        foreach ($this->banks() as $bank) {
            if ($bank['code'] === $bankCode) {
                return $bank['name'];
            }
        }

        return null;
    }

    /**
     * Ask Paystack who owns an account number. This is what makes
     * automatic approval safe: the name comes from the bank, not the form.
     *
     * @return array{account_number: string, account_name: string}|null
     */
    public function resolveAccount(string $accountNumber, string $bankCode): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = $this->client()->get($this->endpoint('/bank/resolve'), [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || ! $response->json('status')) {
            return null;
        }

        $name = $response->json('data.account_name');

        if (! filled($name)) {
            return null;
        }

        return [
            'account_number' => (string) $response->json('data.account_number', $accountNumber),
            'account_name' => (string) $name,
        ];
    }

    /**
     * Create the split-settlement subaccount money will be paid into.
     * percentage_charge mirrors the platform fee; every transaction still
     * overrides it with an exact transaction_charge in kobo.
     */
    public function createSubaccount(
        string $businessName,
        string $bankCode,
        string $accountNumber,
        ?string $contactEmail = null,
        ?string $contactName = null,
        ?string $contactPhone = null,
    ): array {
        $response = $this->client()->post($this->endpoint('/subaccount'), array_filter([
            'business_name' => $businessName,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'percentage_charge' => round(((int) config('hotfii.commerce.platform_fee_bps')) / 100, 2),
            'primary_contact_email' => $contactEmail,
            'primary_contact_name' => $contactName,
            'primary_contact_phone' => $contactPhone,
        ], fn ($value): bool => $value !== null));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message', 'Paystack could not create the settlement subaccount.'));
        }

        return $response->json('data');
    }

    /**
     * Re-point an existing subaccount at new settlement details, so a
     * resubmitted profile does not leave orphan subaccounts behind.
     */
    public function updateSubaccount(
        string $subaccountCode,
        string $businessName,
        string $bankCode,
        string $accountNumber,
        ?string $contactEmail = null,
        ?string $contactName = null,
        ?string $contactPhone = null,
    ): array {
        $response = $this->client()->put($this->endpoint('/subaccount/'.urlencode($subaccountCode)), array_filter([
            'business_name' => $businessName,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'primary_contact_email' => $contactEmail,
            'primary_contact_name' => $contactName,
            'primary_contact_phone' => $contactPhone,
        ], fn ($value): bool => $value !== null));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message', 'Paystack could not update the settlement subaccount.'));
        }

        return $response->json('data');
    }

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

    /**
     * Start a checkout for a platform invoice — the organization paying HotFii,
     * rather than a guest paying the organization.
     *
     * Deliberately no subaccount and no transaction_charge: this money is owed
     * to the platform in full, so splitting any of it back to the tenant's
     * settlement account would refund them their own bill.
     */
    public function initializeInvoice(Invoice $invoice, string $reference, string $email, string $callbackUrl): array
    {
        $response = Http::withToken((string) config('services.paystack.secret'))
            ->acceptJson()
            ->post(rtrim((string) config('services.paystack.url'), '/').'/transaction/initialize', [
                'email' => $email,
                'amount' => $invoice->total_kobo,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'invoice_uuid' => $invoice->uuid,
                    'invoice_number' => $invoice->number,
                    'organization_uuid' => $invoice->organization?->uuid,
                    // Read by ProcessPaystackWebhook to tell an invoice
                    // settlement apart from a guest access sale.
                    'hotfii_purpose' => 'invoice',
                ],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message', 'Paystack could not start this invoice payment.'));
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

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) config('services.paystack.secret'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.paystack.url'), '/').$path;
    }
}