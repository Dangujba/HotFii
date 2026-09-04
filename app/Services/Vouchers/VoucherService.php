<?php

namespace App\Services\Vouchers;

use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\VoucherStatus;
use App\Events\VoucherActivated;
use App\Models\AccessPlan;
use App\Models\Customer;
use App\Models\FeeLedgerEntry;
use App\Models\Organization;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Billing\CommerceFeeCalculator;
use App\Services\Billing\TrialManager;
use App\Services\Radius\RadiusCredentialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VoucherService
{
    public function __construct(
        private readonly RadiusCredentialService $credentials,
        private readonly CommerceFeeCalculator $fees,
        private readonly TrialManager $trials,
    ) {}

    public function createBatch(Organization $organization, AccessPlan $plan, int $quantity, ?int $priceKobo = null): VoucherBatch
    {
        if ($quantity < 1 || $quantity > 5000) {
            throw new RuntimeException('Voucher quantity must be between 1 and 5,000.');
        }

        return DB::transaction(function () use ($organization, $plan, $quantity, $priceKobo) {
            $batch = VoucherBatch::create([
                'organization_id' => $organization->id,
                'access_plan_id' => $plan->id,
                'reference' => 'VB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'quantity' => $quantity,
                'retail_price_kobo' => $priceKobo ?? $plan->price_kobo,
                'status' => VoucherStatus::Generated->value,
            ]);

            for ($index = 0; $index < $quantity; $index++) {
                $code = $this->newCode();
                $batch->vouchers()->create([
                    'organization_id' => $organization->id,
                    'code_lookup' => $this->lookup($code),
                    'code_cipher' => $code,
                    'code_last_four' => substr($code, -4),
                    'status' => VoucherStatus::Generated,
                    'price_snapshot_kobo' => $batch->retail_price_kobo,
                ]);
            }

            return $batch->load('vouchers', 'accessPlan');
        });
    }

    public function redeem(Organization $organization, string $code, ?string $phone = null): Voucher
    {
        return DB::transaction(function () use ($organization, $code, $phone) {
            $voucher = Voucher::query()
                ->where('organization_id', $organization->id)
                ->where('code_lookup', $this->lookup($code))
                ->lockForUpdate()
                ->firstOrFail();

            if ($voucher->expires_at?->isPast()) {
                $voucher->update(['status' => VoucherStatus::Expired]);
            }

            if (in_array($voucher->status, [VoucherStatus::Expired, VoucherStatus::Revoked], true)) {
                throw new RuntimeException('This voucher is no longer valid.');
            }

            if ($voucher->status === VoucherStatus::Active) {
                throw new RuntimeException('This voucher has already been activated.');
            }

            $plan = $voucher->batch->accessPlan;
            if ($organization->status === OrganizationStatus::Suspended && ! $voucher->is_complimentary) {
                throw new RuntimeException('Paid voucher activation is unavailable while the organization is suspended.');
            }
            if (! $organization->trial_started_at
                && $organization->networkDevices()->where('status', 'online')->exists()) {
                $organization = $this->trials->start($organization);
            }
            $customer = $phone
                ? Customer::firstOrCreate(
                    ['organization_id' => $organization->id, 'phone' => $phone],
                    ['type' => 'customer', 'status' => 'active'],
                )
                : null;

            $wasRecordedSold = $voucher->sold_at !== null;
            $expiresAt = $plan->validity_days ? now()->addDays($plan->validity_days) : null;

            $voucher->update([
                'customer_id' => $customer?->id,
                'status' => VoucherStatus::Active,
                'activated_at' => now(),
                'sold_at' => $voucher->sold_at ?? now(),
                'expires_at' => $expiresAt,
            ]);

            $this->credentials->issue(
                $organization,
                $plan,
                $customer,
                $voucher,
                $voucher->uuid,
                $voucher->code_cipher,
            );

            if (! $voucher->is_complimentary && $voucher->price_snapshot_kobo > 0) {
                $quote = $this->fees->quote($organization, $voucher->price_snapshot_kobo);
                FeeLedgerEntry::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'source_type' => 'voucher',
                        'source_id' => $voucher->id,
                    ],
                    [
                        'billing_period' => now()->startOfMonth()->toDateString(),
                        'billable_sales_kobo' => $voucher->price_snapshot_kobo,
                        'fee_amount_kobo' => $quote->chargeablePercentageFeeKobo(),
                        'status' => 'accrued',
                        'metadata' => ['unrecorded_sale' => ! $wasRecordedSold],
                    ],
                );
            }

            $voucher = $voucher->refresh()->load('credential', 'batch.accessPlan');
            VoucherActivated::dispatch($voucher);
            return $voucher;
        });
    }

    private function newCode(): string
    {
        do {
            $code = 'HF-'.implode('-', str_split(Str::upper(Str::random(12)), 4));
        } while (Voucher::where('code_lookup', $this->lookup($code))->exists());

        return $code;
    }

    private function lookup(string $code): string
    {
        return hash_hmac('sha256', Str::upper(trim($code)), (string) config('app.key'));
    }
}