<?php

namespace App\Services\Sales;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Models\AccessPlan;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Billing\CommerceFeeCalculator;
use App\Services\Billing\TrialManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DirectCashSaleService
{
    public function __construct(
        private readonly CommerceFeeCalculator $fees,
        private readonly TrialManager $trials,
        private readonly PaymentProcessor $processor,
    ) {}

    /** @return array{transaction: Transaction, username: ?string, password: ?string, created: bool} */
    public function record(
        Organization $organization,
        AccessPlan $plan,
        NetworkDevice $device,
        ?string $customerName = null,
        ?string $phone = null,
        ?string $requestId = null,
    ): array {
        $name = filled($customerName) ? trim((string) $customerName) : null;
        $number = filled($phone) ? trim((string) $phone) : null;
        $reference = $requestId
            ? 'HF-CASH-'.Str::upper(str_replace('-', '', $requestId))
            : 'HF-CASH-'.Str::upper(Str::random(12));
        $fingerprint = $requestId ? hash('sha256', json_encode([
            'plan' => $plan->id,
            'device' => $device->id,
            'name' => $name,
            'phone' => $number,
        ], JSON_THROW_ON_ERROR)) : null;

        if ($requestId && $existing = $organization->transactions()->where('reference', $reference)->first()) {
            return $this->result($existing, $fingerprint, false);
        }

        if (! $organization->sellsAccess()) {
            throw ValidationException::withMessages(['organization' => 'This organization does not sell guest access.']);
        }
        if (in_array($organization->status, [OrganizationStatus::Suspended, OrganizationStatus::Grace], true)) {
            throw ValidationException::withMessages(['organization' => 'New paid activations are unavailable while billing is overdue.']);
        }
        if ($plan->organization_id !== $organization->id || $plan->access_type !== 'paid' || ! $plan->is_active) {
            throw ValidationException::withMessages(['access_plan_id' => 'Choose an active paid plan from this organization.']);
        }
        if ($device->organization_id !== $organization->id || $device->status !== NetworkDeviceStatus::Online) {
            throw ValidationException::withMessages(['network_device_id' => 'Choose an online, fully tested router from this organization.']);
        }

        try {
            return DB::transaction(function () use ($organization, $plan, $device, $name, $number, $reference, $requestId, $fingerprint) {
                if (! $organization->trial_started_at) {
                    $organization = $this->trials->start($organization);
                }

                if ($number !== null) {
                    $customer = Customer::firstOrCreate(
                        ['organization_id' => $organization->id, 'phone' => $number],
                        ['name' => $name, 'type' => 'customer', 'status' => 'active'],
                    );
                    if ($name !== null && blank($customer->name)) {
                        $customer->update(['name' => $name]);
                    }
                } else {
                    $customer = $organization->customers()->create([
                        'name' => $name,
                        'type' => 'customer',
                        'status' => 'active',
                    ]);
                }

                $quote = $this->fees->quote($organization, $plan->price_kobo);
                $transaction = Transaction::create([
                    'organization_id' => $organization->id,
                    'network_device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'access_plan_id' => $plan->id,
                    'reference' => $reference,
                    'provider' => 'manual',
                    'channel' => 'cash',
                    'status' => PaymentStatus::Pending,
                    'gross_amount_kobo' => $plan->price_kobo,
                    'platform_fee_kobo' => $quote->chargeablePercentageFeeKobo(),
                    'billable_sales_kobo' => $plan->price_kobo,
                    'metadata' => $requestId ? [
                        'mobile_request_id' => $requestId,
                        'idempotency_fingerprint' => $fingerprint,
                    ] : null,
                ]);

                $transaction = $this->processor->markSuccessful($transaction, [
                    'amount' => $plan->price_kobo,
                    'fees' => 0,
                    'channel' => 'cash',
                ]);

                return $this->result($transaction, $fingerprint, true);
            });
        } catch (QueryException $exception) {
            $existing = $requestId
                ? $organization->transactions()->where('reference', $reference)->first()
                : null;
            if (! $existing) {
                throw $exception;
            }

            return $this->result($existing, $fingerprint, false);
        }
    }

    /** @return array{transaction: Transaction, username: ?string, password: ?string, created: bool} */
    private function result(Transaction $transaction, ?string $fingerprint, bool $created): array
    {
        if ($fingerprint && ! hash_equals((string) data_get($transaction->metadata, 'idempotency_fingerprint'), $fingerprint)) {
            throw ValidationException::withMessages([
                'request_id' => 'This request ID was already used for different cash-sale details.',
            ]);
        }

        $credential = $transaction->customer?->credentials()->latest()->first();

        return [
            'transaction' => $transaction->load('customer', 'accessPlan', 'networkDevice'),
            'username' => $credential?->username,
            'password' => $credential?->password_cipher,
            'created' => $created,
        ];
    }
}
