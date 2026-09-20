<?php

namespace Tests\Feature\Billing;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\VoucherStatus;
use App\Models\FeeLedgerEntry;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherTransactionBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_a_missing_voucher_transaction_once(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $organization = Organization::create([
            'name' => 'Bala Test',
            'slug' => 'bala-test',
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::StandardSeller,
        ]);
        $plan = $organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 50000,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
        ]);
        $voucher = app(VoucherService::class)->createBatch($organization, $plan, 1)->vouchers->first();
        $activatedAt = now()->subDay();
        $voucher->update([
            'status' => VoucherStatus::Active,
            'sold_at' => $activatedAt,
            'activated_at' => $activatedAt,
        ]);

        FeeLedgerEntry::create([
            'organization_id' => $organization->id,
            'source_type' => 'voucher',
            'source_id' => $voucher->id,
            'billing_period' => $activatedAt->copy()->startOfMonth()->toDateString(),
            'billable_sales_kobo' => 50000,
            'fee_amount_kobo' => 1500,
            'status' => 'accrued',
        ]);

        $this->artisan('hotfii:backfill-voucher-transactions', ['--commit' => true])
            ->assertSuccessful();

        $transaction = Transaction::sole();
        $this->assertSame('cash', $transaction->channel);
        $this->assertSame(50000, $transaction->gross_amount_kobo);
        $this->assertSame(1500, $transaction->platform_fee_kobo);
        $this->assertSame($activatedAt->toDateTimeString(), $transaction->paid_at->toDateTimeString());
        $this->assertSame($activatedAt->toDateTimeString(), $transaction->created_at->toDateTimeString());

        $this->artisan('hotfii:backfill-voucher-transactions', ['--commit' => true])
            ->expectsOutput('No voucher transactions are missing.')
            ->assertSuccessful();

        $this->assertSame(1, Transaction::count());
    }
}
