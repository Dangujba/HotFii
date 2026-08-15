<?php

namespace App\Services\Payments;

use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Models\FeeLedgerEntry;
use App\Models\Transaction;
use App\Services\Billing\TrialManager;
use App\Services\Radius\RadiusCredentialService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentProcessor
{
    public function __construct(
        private readonly RadiusCredentialService $credentials,
        private readonly TrialManager $trials,
    ) {}

    public function markSuccessful(Transaction $transaction, array $providerData): Transaction
    {
        return DB::transaction(function () use ($transaction, $providerData) {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status === PaymentStatus::Successful) {
                return $transaction;
            }

            if ((int) ($providerData['amount'] ?? 0) !== $transaction->gross_amount_kobo) {
                throw new RuntimeException('Payment amount does not match the HotFii transaction.');
            }

            $transaction->update([
                'status' => PaymentStatus::Successful,
                'gateway_fee_kobo' => (int) ($providerData['fees'] ?? 0),
                'provider_response' => $providerData,
                'paid_at' => now(),
            ]);

            $organization = $transaction->organization;
            if (! $organization->trial_started_at && $organization->status === OrganizationStatus::Live) {
                $organization = $this->trials->start($organization);
            }

            $organization->increment('monthly_sales_kobo', $transaction->billable_sales_kobo);
            if ($organization->inTrial()) {
                $organization->increment('trial_sales_kobo', $transaction->billable_sales_kobo);
            }

            if ($transaction->accessPlan) {
                $this->credentials->issue($organization, $transaction->accessPlan, $transaction->customer);
            }

            FeeLedgerEntry::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'source_type' => 'transaction',
                    'source_id' => $transaction->id,
                ],
                [
                    'billing_period' => now()->startOfMonth()->toDateString(),
                    'billable_sales_kobo' => $transaction->billable_sales_kobo,
                    'fee_amount_kobo' => $transaction->platform_fee_kobo,
                    'status' => 'collected',
                    'metadata' => ['provider' => $transaction->provider, 'channel' => $transaction->channel],
                ],
            );

            $transaction = $transaction->refresh();
            PaymentStatusChanged::dispatch($transaction);
            return $transaction;
        });
    }

    public function markFailed(Transaction $transaction, array $providerData = []): Transaction
    {
        if ($transaction->status !== PaymentStatus::Successful) {
            $transaction->update(['status' => PaymentStatus::Failed, 'provider_response' => $providerData]);
            PaymentStatusChanged::dispatch($transaction->refresh());
        }
        return $transaction;
    }
}