<?php

namespace Tests\Feature\Billing;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Jobs\EnforceSubscriptionGrace;
use App\Jobs\GenerateMonthlyInvoices;
use App\Models\AuditLog;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Billing\InvoiceSettlement;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The billing loop, end to end.
 *
 * Three faults are guarded here, each of which silently cost real money:
 *
 * 1. A cash sale recorded its fee as 'collected'. GenerateMonthlyInvoices
 *    subtracts collected fees from the bill, so cash sales did not merely
 *    under-bill — they cancelled the invoice out, minimum included, and a
 *    cash-only operator was billed nothing at all with no invoice to show it.
 * 2. Nothing anywhere could mark an invoice paid, so an organization that had
 *    paid stayed suspended forever.
 * 3. EnforceSubscriptionGrace re-suspended on every hourly run, which made the
 *    console's reactivate button pointless while an invoice was open.
 */
class InvoiceSettlementTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHLY_MINIMUM_KOBO = 250000; // ₦2,500, config('hotfii.commerce.standard_minimum_kobo')

    private function seller(?string $subaccount = 'ACCT_test1234567'): Organization
    {
        $organization = Organization::create([
            'name' => 'Damaturu Mesh',
            'slug' => 'damaturu-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::StandardSeller,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        // Past the trial, so fees are actually chargeable.
        $organization->forceFill([
            'trial_started_at' => now()->subMonths(3),
            'trial_ends_at' => now()->subMonths(2),
            'paystack_subaccount_code' => $subaccount,
            'live_payments_enabled_at' => $subaccount ? now()->subMonths(3) : null,
        ])->save();

        return $organization->refresh();
    }

    private function sale(Organization $organization, int $kobo, string $provider, string $channel): Transaction
    {
        return Transaction::create([
            'organization_id' => $organization->id,
            'reference' => 'HF-'.Str::upper(Str::random(12)),
            'provider' => $provider,
            'channel' => $channel,
            'status' => PaymentStatus::Pending,
            'gross_amount_kobo' => $kobo,
            // 2% — config('hotfii.commerce.platform_fee_bps') is 200.
            'platform_fee_kobo' => intdiv($kobo * 200, 10_000),
            'billable_sales_kobo' => $kobo,
        ]);
    }

    private function overdueInvoice(Organization $organization, string $period = '2026-08-01'): Invoice
    {
        return Invoice::create([
            'organization_id' => $organization->id,
            'number' => 'HF-INV-'.Str::upper(Str::random(10)),
            'billing_period' => $period,
            'subtotal_kobo' => 250000,
            'total_kobo' => 250000,
            'status' => 'open',
            'due_at' => now()->subDays(30),
        ]);
    }

    public function test_a_cash_sale_accrues_its_fee_rather_than_marking_it_collected(): void
    {
        $organization = $this->seller();
        $transaction = $this->sale($organization, 20_000_00, 'manual', 'cash');

        app(PaymentProcessor::class)->markSuccessful($transaction, ['amount' => 20_000_00]);

        $entry = FeeLedgerEntry::where('source_id', $transaction->id)->where('source_type', 'transaction')->firstOrFail();

        $this->assertSame(
            'accrued',
            $entry->status,
            'Cash never passed through a gateway, so the fee is owed. Marked collected it is subtracted from the invoice.',
        );
    }

    public function test_an_online_sale_still_records_its_fee_as_collected(): void
    {
        $organization = $this->seller();
        $transaction = $this->sale($organization, 20_000_00, 'paystack', 'online');

        app(PaymentProcessor::class)->markSuccessful($transaction, ['amount' => 20_000_00]);

        $entry = FeeLedgerEntry::where('source_id', $transaction->id)->where('source_type', 'transaction')->firstOrFail();

        $this->assertSame('collected', $entry->status, 'Paystack split this fee out at the point of sale.');
    }

    public function test_a_sale_without_a_settlement_subaccount_is_not_treated_as_split(): void
    {
        // No subaccount means no transaction_charge was sent, so Paystack split
        // nothing — see PaystackService::initialize().
        $organization = $this->seller(subaccount: null);
        $transaction = $this->sale($organization, 20_000_00, 'paystack', 'online');

        app(PaymentProcessor::class)->markSuccessful($transaction, ['amount' => 20_000_00]);

        $this->assertSame('accrued', FeeLedgerEntry::where('source_id', $transaction->id)->firstOrFail()->status);
    }

    public function test_a_month_of_cash_sales_produces_an_invoice(): void
    {
        $organization = $this->seller();
        $period = now()->subMonth()->startOfMonth();

        // ₦200,000 in cash: 2% is ₦4,000, well above the ₦2,500 minimum.
        FeeLedgerEntry::create([
            'organization_id' => $organization->id,
            'source_type' => 'transaction',
            'source_id' => 1,
            'billing_period' => $period->toDateString(),
            'billable_sales_kobo' => 200_000_00,
            'fee_amount_kobo' => 4_000_00,
            'status' => 'accrued',
        ]);

        (new GenerateMonthlyInvoices($period->toDateString()))->handle();

        $invoice = Invoice::where('organization_id', $organization->id)->first();

        $this->assertNotNull($invoice, 'A cash-only month must still be billed. It used to produce no invoice at all.');
        $this->assertSame(4_000_00, $invoice->total_kobo);
    }

    public function test_fees_already_taken_at_the_gateway_are_not_billed_twice(): void
    {
        $organization = $this->seller();
        $period = now()->subMonth()->startOfMonth();

        FeeLedgerEntry::create([
            'organization_id' => $organization->id,
            'source_type' => 'transaction',
            'source_id' => 1,
            'billing_period' => $period->toDateString(),
            'billable_sales_kobo' => 200_000_00,
            'fee_amount_kobo' => 4_000_00,
            'status' => 'collected',
        ]);

        (new GenerateMonthlyInvoices($period->toDateString()))->handle();

        $this->assertNull(
            Invoice::where('organization_id', $organization->id)->first(),
            'The gateway already took the whole fee, so there is nothing left to invoice.',
        );
    }

    public function test_an_overdue_invoice_suspends_the_account_and_records_why(): void
    {
        $organization = $this->seller();
        $this->overdueInvoice($organization);

        (new EnforceSubscriptionGrace)->handle();

        $organization->refresh();

        $this->assertSame(OrganizationStatus::Suspended, $organization->status);
        $this->assertNotNull(
            $organization->billing_suspended_at,
            'Without this mark the restriction cannot tell itself apart from a manual suspension, so it can never lift.',
        );
        $this->assertFalse($organization->canCollectLivePayments());
    }

    public function test_settling_the_invoice_lifts_the_suspension(): void
    {
        $organization = $this->seller();
        $invoice = $this->overdueInvoice($organization);
        (new EnforceSubscriptionGrace)->handle();

        $this->assertSame(OrganizationStatus::Suspended, $organization->refresh()->status);

        app(InvoiceSettlement::class)->settle($invoice, 'manual', 'NIBSS-99887766');

        $organization->refresh();

        $this->assertTrue($invoice->refresh()->isPaid());
        $this->assertSame(OrganizationStatus::Live, $organization->status);
        $this->assertNull($organization->billing_suspended_at);
        $this->assertTrue($organization->canCollectLivePayments(), 'A paid-up organization must be able to sell again.');
    }

    public function test_settling_one_of_two_overdue_invoices_keeps_the_account_restricted(): void
    {
        $organization = $this->seller();
        $first = $this->overdueInvoice($organization, '2026-07-01');
        $this->overdueInvoice($organization, '2026-08-01');
        (new EnforceSubscriptionGrace)->handle();

        app(InvoiceSettlement::class)->settle($first, 'manual', 'NIBSS-1');

        $this->assertSame(OrganizationStatus::Suspended, $organization->refresh()->status);
        $this->assertNotNull($organization->billing_suspended_at);
    }

    public function test_the_hourly_job_does_not_lift_a_suspension_imposed_from_the_console(): void
    {
        $organization = $this->seller();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->patch(route('platform.organizations.status', $organization), [
            'action' => 'suspend',
            'reason' => 'Chargebacks on every sale since Tuesday.',
        ]);

        // Nothing is overdue, so the restore pass sees an eligible-looking
        // account. It must leave this one alone: a person decided it.
        (new EnforceSubscriptionGrace)->handle();

        $this->assertSame(OrganizationStatus::Suspended, $organization->refresh()->status);
    }

    public function test_reactivating_from_the_console_warns_when_an_invoice_is_still_overdue(): void
    {
        $organization = $this->seller();
        $this->overdueInvoice($organization);
        (new EnforceSubscriptionGrace)->handle();

        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'reactivate',
                'reason' => 'Owner says the transfer was sent on Monday.',
            ])
            ->assertSessionHas('error');

        // And the next run does exactly what the warning said it would.
        (new EnforceSubscriptionGrace)->handle();

        $this->assertSame(OrganizationStatus::Suspended, $organization->refresh()->status);
    }

    public function test_the_platform_owner_can_record_a_transfer_against_an_invoice(): void
    {
        $organization = $this->seller();
        $invoice = $this->overdueInvoice($organization);
        (new EnforceSubscriptionGrace)->handle();

        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('platform.invoices.pay', $invoice), [
                'reference' => 'NIBSS-99887766',
                'reason' => 'Transfer confirmed on the GTB statement this morning.',
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertTrue($invoice->isPaid());
        $this->assertSame('manual', $invoice->payment_method);
        $this->assertSame('NIBSS-99887766', $invoice->payment_reference);
        $this->assertSame(OrganizationStatus::Live, $organization->refresh()->status);

        $log = AuditLog::where('action', 'invoice.paid')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Transfer confirmed on the GTB statement this morning.', $log->reason);
    }

    public function test_recording_a_payment_requires_a_reference_and_a_reason(): void
    {
        $organization = $this->seller();
        $invoice = $this->overdueInvoice($organization);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('platform.invoices.pay', $invoice), ['reference' => '', 'reason' => 'ok'])
            ->assertSessionHasErrors(['reference', 'reason']);

        $this->assertFalse($invoice->refresh()->isPaid());
    }

    public function test_an_invoice_cannot_be_settled_twice(): void
    {
        $organization = $this->seller();
        $invoice = $this->overdueInvoice($organization);
        $settlement = app(InvoiceSettlement::class);

        $this->assertTrue($settlement->settle($invoice, 'paystack', 'HF-INVPAY-ONE'));
        // A callback and its webhook routinely land together.
        $this->assertFalse($settlement->settle($invoice->refresh(), 'paystack', 'HF-INVPAY-ONE'));

        $this->assertSame(1, AuditLog::where('action', 'invoice.paid')->count());
    }

    public function test_an_ordinary_operator_cannot_reach_the_platform_mark_paid_route(): void
    {
        $organization = $this->seller();
        $invoice = $this->overdueInvoice($organization);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('platform.invoices.pay', $invoice), [
                'reference' => 'NIBSS-1',
                'reason' => 'Trying it on from an ordinary account.',
            ])
            ->assertForbidden();

        $this->assertFalse($invoice->refresh()->isPaid());
    }
}
