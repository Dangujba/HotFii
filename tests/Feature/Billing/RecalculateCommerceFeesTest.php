<?php

namespace Tests\Feature\Billing;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Models\FeeLedgerEntry;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecalculateCommerceFeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_billing_and_two_percent_without_claiming_an_old_zero_fee_was_collected(): void
    {
        $organization = Organization::create([
            'name' => 'Cash Only Network',
            'slug' => 'cash-only-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Sandbox,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
            'paystack_subaccount_code' => 'ACCT_historical',
        ]);
        $paidAt = now()->subDays(3)->startOfMinute();
        $transaction = Transaction::create([
            'organization_id' => $organization->id,
            'reference' => 'HF-'.Str::upper(Str::random(12)),
            'provider' => 'paystack',
            'channel' => 'online',
            'status' => PaymentStatus::Successful,
            'gross_amount_kobo' => 500_00,
            'platform_fee_kobo' => 0,
            'billable_sales_kobo' => 500_00,
            'paid_at' => $paidAt,
        ]);
        $entry = FeeLedgerEntry::create([
            'organization_id' => $organization->id,
            'source_type' => 'transaction',
            'source_id' => $transaction->id,
            'billing_period' => $paidAt->copy()->startOfMonth()->toDateString(),
            'billable_sales_kobo' => 500_00,
            'fee_amount_kobo' => 0,
            'status' => 'collected',
        ]);

        $this->artisan('hotfii:recalculate-commerce-fees')->assertSuccessful();
        $this->assertNull($organization->refresh()->trial_started_at);
        $this->assertSame(0, $entry->refresh()->fee_amount_kobo);

        $this->artisan('hotfii:recalculate-commerce-fees', ['--commit' => true])->assertSuccessful();

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Trial, $organization->status);
        $this->assertTrue($organization->trial_started_at->equalTo($paidAt));
        $this->assertSame(500_00, $organization->trial_sales_kobo);
        $this->assertSame(1_000, $entry->refresh()->fee_amount_kobo);
        $this->assertSame('accrued', $entry->status);
        $this->assertSame(1_000, $transaction->refresh()->platform_fee_kobo);

        $this->artisan('hotfii:recalculate-commerce-fees', ['--commit' => true])
            ->expectsOutput('No commerce fees or billing start dates need correction.')
            ->assertSuccessful();
    }
}
