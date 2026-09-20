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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DirectCashSaleService
{
    public function __construct(
        private readonly CommerceFeeCalculator $fees,
        private readonly TrialManager $trials,
        private readonly PaymentProcessor $processor,
    ) {}

    /** @return array{transaction: Transaction, username: ?string, password: ?string} */
    public function record(
        Organization $organization,
        AccessPlan $plan,
        NetworkDevice $device,
        ?string $customerName = null,
        ?string $phone = null,
    ): array {
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

        if (! $organization->trial_started_at) {
            $organization = $this->trials->start($organization);
        }

        $name = filled($customerName) ? trim((string) $customerName) : null;
        $number = filled($phone) ? trim((string) $phone) : null;
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
            'reference' => 'HF-CASH-'.Str::upper(Str::random(12)),
            'provider' => 'manual',
            'channel' => 'cash',
            'status' => PaymentStatus::Pending,
            'gross_amount_kobo' => $plan->price_kobo,
            'platform_fee_kobo' => $quote->chargeablePercentageFeeKobo(),
            'billable_sales_kobo' => $plan->price_kobo,
        ]);

        $transaction = $this->processor->markSuccessful($transaction, [
            'amount' => $plan->price_kobo,
            'fees' => 0,
            'channel' => 'cash',
        ]);
        $credential = $customer->credentials()->latest()->first();

        return [
            'transaction' => $transaction->load('customer', 'accessPlan', 'networkDevice'),
            'username' => $credential?->username,
            'password' => $credential?->password_cipher,
        ];
    }
}
