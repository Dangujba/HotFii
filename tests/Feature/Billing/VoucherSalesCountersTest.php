<?php

namespace Tests\Feature\Billing;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\AccessPlan;
use App\Models\FeeLedgerEntry;
use App\Models\Organization;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A redeemed voucher is a sale, and must move every counter a card sale moves.
 *
 * It did not. The fee ledger was written correctly — invoices were never wrong —
 * but monthly_sales_kobo and trial_sales_kobo stayed at zero, and those are what
 * graduate an operator off MicroSeller pricing and enforce the trial ceiling. A
 * voucher-only operator therefore looked like a business with no sales: priced
 * as a micro seller forever, and able to sell straight through the trial cap.
 * For a cash-and-voucher market that is not an edge case, it is the norm.
 */
class VoucherSalesCountersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Voucher codes are looked up by an HMAC keyed on app.key.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function seller(BillingPlan $plan = BillingPlan::StandardSeller): Organization
    {
        $organization = Organization::create([
            'name' => 'Damaturu Mesh',
            'slug' => 'damaturu-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => $plan,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        // Past the trial, so fees are chargeable rather than waived.
        $organization->forceFill([
            'trial_started_at' => now()->subMonths(3),
            'trial_ends_at' => now()->subMonths(2),
        ])->save();

        return $organization->refresh();
    }

    private function plan(Organization $organization, int $priceKobo = 50000): AccessPlan
    {
        return $organization->accessPlans()->create([
            'name' => 'Two Hours',
            'access_type' => 'paid',
            'price_kobo' => $priceKobo,
            'duration_minutes' => 120,
            'simultaneous_use' => 1,
        ]);
    }

    private function redeemOne(Organization $organization, AccessPlan $plan, int $quantity = 1, ?int $priceKobo = null): void
    {
        $service = app(VoucherService::class);
        $batch = $service->createBatch($organization, $plan, $quantity, $priceKobo);

        foreach ($batch->vouchers as $voucher) {
            $service->redeem($organization, $voucher->code_cipher);
        }
    }

    public function test_redeeming_a_voucher_counts_towards_monthly_sales(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);

        $this->redeemOne($organization, $plan);

        $this->assertSame(
            50000,
            $organization->refresh()->monthly_sales_kobo,
            'Voucher sales were invisible to plan graduation, so a micro seller never outgrew micro pricing.',
        );
    }

    public function test_printing_vouchers_charges_and_counts_nothing(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);

        app(VoucherService::class)->createBatch($organization, $plan, 100);

        $this->assertSame(0, $organization->refresh()->monthly_sales_kobo, 'Inventory is not a sale.');
        $this->assertSame(0, FeeLedgerEntry::where('organization_id', $organization->id)->count());
    }

    public function test_each_redemption_adds_to_the_running_total(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);

        $this->redeemOne($organization, $plan, quantity: 3);

        $this->assertSame(150000, $organization->refresh()->monthly_sales_kobo);
    }

    public function test_the_frozen_voucher_price_is_what_counts_not_the_current_plan_price(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);

        // Printed at ₦500, then the plan is raised to ₦800. The customer paid
        // ₦500, so ₦500 is the sale.
        $this->redeemOne($organization, $plan, priceKobo: 50000);
        $plan->update(['price_kobo' => 80000]);

        $this->assertSame(50000, $organization->refresh()->monthly_sales_kobo);
    }

    public function test_a_trial_redemption_counts_towards_the_trial_ceiling(): void
    {
        $organization = $this->seller();
        $organization->forceFill([
            'status' => OrganizationStatus::Trial,
            'trial_started_at' => now()->subDays(2),
            'trial_ends_at' => now()->addDays(12),
            'trial_sales_kobo' => 0,
        ])->save();

        $plan = $this->plan($organization->refresh());

        $this->redeemOne($organization, $plan);

        $organization->refresh();

        $this->assertSame(
            50000,
            $organization->trial_sales_kobo,
            'The trial ceiling could not see voucher sales, so a trial could sell without limit.',
        );
        // Both counters move: the ceiling is per-trial, graduation is per-month.
        $this->assertSame(50000, $organization->monthly_sales_kobo);
    }

    public function test_a_settled_account_does_not_touch_the_trial_counter(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);

        $this->redeemOne($organization, $plan);

        $this->assertSame(0, $organization->refresh()->trial_sales_kobo, 'The trial is long over.');
    }

    public function test_a_complimentary_voucher_counts_as_no_sale(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);
        $service = app(VoucherService::class);

        $batch = $service->createBatch($organization, $plan, 1);
        $voucher = $batch->vouchers->first();
        $voucher->update(['is_complimentary' => true]);

        $service->redeem($organization, $voucher->code_cipher);

        $organization->refresh();

        $this->assertSame(0, $organization->monthly_sales_kobo, 'A giveaway is not revenue.');
        $this->assertSame(0, FeeLedgerEntry::where('organization_id', $organization->id)->count());
    }

    public function test_a_free_plan_voucher_counts_as_no_sale(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization, priceKobo: 0);

        $this->redeemOne($organization, $plan, priceKobo: 0);

        $this->assertSame(0, $organization->refresh()->monthly_sales_kobo);
    }

    public function test_a_voucher_cannot_be_counted_twice(): void
    {
        $organization = $this->seller();
        $plan = $this->plan($organization);
        $service = app(VoucherService::class);

        $batch = $service->createBatch($organization, $plan, 1);
        $code = $batch->vouchers->first()->code_cipher;

        $service->redeem($organization, $code);

        try {
            $service->redeem($organization, $code);
        } catch (\RuntimeException) {
            // Expected: an active voucher is refused.
        }

        $this->assertSame(
            50000,
            $organization->refresh()->monthly_sales_kobo,
            'A second activation attempt must not bill or count the sale again.',
        );
        $this->assertSame(1, FeeLedgerEntry::where('organization_id', $organization->id)->count());
    }
}
