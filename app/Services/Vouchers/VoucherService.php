<?php

namespace App\Services\Vouchers;

use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\VoucherPinFormat;
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
    private const PIN_LENGTH = 12;

    public function __construct(
        private readonly RadiusCredentialService $credentials,
        private readonly CommerceFeeCalculator $fees,
        private readonly TrialManager $trials,
        private readonly VoucherSaleTransactionRecorder $sales,
    ) {}

    public function createBatch(
        Organization $organization,
        AccessPlan $plan,
        int $quantity,
        ?int $priceKobo = null,
        VoucherPinFormat $pinFormat = VoucherPinFormat::Numbers,
        bool $dashedPin = true,
    ): VoucherBatch {
        if ($quantity < 1 || $quantity > 5000) {
            throw new RuntimeException('Voucher quantity must be between 1 and 5,000.');
        }

        // An operator may mark a voucher up, but may not reduce the sale below
        // the paid plan it grants. Otherwise a ₦500 plan could be reported as
        // a ₦100 sale while the operator takes the difference off-system.
        // Keep this in the service so imports and future callers cannot bypass
        // the same rule enforced by the web form.
        if ($priceKobo !== null && $priceKobo < $plan->price_kobo) {
            throw new RuntimeException('A voucher cannot be priced below its access plan. Leave the price blank to use the plan price.');
        }

        return DB::transaction(function () use ($organization, $plan, $quantity, $priceKobo, $pinFormat, $dashedPin) {
            $batch = VoucherBatch::create([
                'organization_id' => $organization->id,
                'access_plan_id' => $plan->id,
                'reference' => 'VB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'quantity' => $quantity,
                'retail_price_kobo' => $priceKobo ?? $plan->price_kobo,
                'status' => VoucherStatus::Generated->value,
            ]);

            for ($index = 0; $index < $quantity; $index++) {
                $code = $this->newCode($pinFormat, $dashedPin);
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

            // Last line of defence for the batch-pricing hole above. A paid
            // voucher that reaches activation worth under ₦1 would otherwise
            // activate silently and skip every sales counter and fee entry, so
            // the operator sees ₦0 sales and HotFii bills nothing. Refusing is
            // the lesser harm: the operator hits a clear error and can reprice
            // the batch, rather than giving away access and revenue unnoticed.
            if (
                ! $voucher->is_complimentary
                && $plan->access_type === 'paid'
                && $voucher->price_snapshot_kobo < 100
            ) {
                throw new RuntimeException('This voucher was generated with an invalid price and cannot be activated. Regenerate the batch at the correct price.');
            }

            if ($organization->status === OrganizationStatus::Suspended && ! $voucher->is_complimentary) {
                throw new RuntimeException('Paid voucher activation is unavailable while the organization is suspended.');
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
                // Redeeming a paid voucher is the commercial start signal even
                // for a cash-only operator with no payment profile or online
                // router. Trial tracks onboarding; it never waives the fee.
                if (! $organization->trial_started_at) {
                    $organization = $this->trials->start($organization);
                }

                // The same two counters a card sale moves in PaymentProcessor.
                // Without them a voucher-only operator reads as a zero-sales
                // business to everything downstream: they never graduate off
                // MicroSeller pricing however much they sell, and the trial
                // ceiling never trips because it can see no sales at all. For a
                // cash-and-voucher market that is most of the volume.
                $organization->increment('monthly_sales_kobo', $voucher->price_snapshot_kobo);
                if ($organization->inTrial()) {
                    $organization->increment('trial_sales_kobo', $voucher->price_snapshot_kobo);
                }

                $quote = $this->fees->quote($organization, $voucher->price_snapshot_kobo);
                $this->sales->record($voucher, $quote->chargeablePercentageFeeKobo());

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

    private function newCode(VoucherPinFormat $format, bool $dashed): string
    {
        $alphabet = $format->alphabet();
        $highest = strlen($alphabet) - 1;

        do {
            $pin = '';
            for ($position = 0; $position < self::PIN_LENGTH; $position++) {
                $pin .= $alphabet[random_int(0, $highest)];
            }
            $code = $dashed ? implode('-', str_split($pin, 4)) : $pin;
        } while (Voucher::where('code_lookup', $this->lookup($code))->exists());

        return $code;
    }

    private function lookup(string $code): string
    {
        return hash_hmac('sha256', Str::upper(trim($code)), (string) config('app.key'));
    }
}
