<?php

namespace Tests\Feature\Platform;

use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\PaymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the invariant that approval actually enables collection.
 *
 * live_payments_enabled_at is not fillable, so any future caller that reaches
 * for update() instead of Organization::activateLivePayments() reintroduces the
 * production bug where an approved organization is Live, holds a subaccount
 * code, and is refused on every sale. In this environment that write throws;
 * in production it was discarded in silence. These tests assert the outcome
 * rather than the mechanism, so they fail either way.
 */
class LivePaymentActivationTest extends TestCase
{
    use RefreshDatabase;

    private function organization(OrganizationStatus $status = OrganizationStatus::Sandbox): Organization
    {
        return Organization::create([
            'name' => 'Damaturu Mesh',
            'slug' => 'damaturu-mesh-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => $status,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }

    public function test_activating_live_payments_lets_the_organization_collect(): void
    {
        $organization = $this->organization();

        $organization->activateLivePayments('ACCT_test1234567');

        $organization->refresh();

        $this->assertSame(OrganizationStatus::Live, $organization->status);
        $this->assertSame('ACCT_test1234567', $organization->paystack_subaccount_code);
        $this->assertNotNull(
            $organization->live_payments_enabled_at,
            'The activation timestamp was discarded, so the organization cannot collect.',
        );

        // The point of the whole thing: approved means it can actually sell.
        $this->assertTrue($organization->canCollectLivePayments());
        $this->assertTrue($organization->paymentProfileActivated());
    }

    public function test_activation_can_be_backdated_so_billing_windows_stay_honest(): void
    {
        $organization = $this->organization();
        $approvedAt = now()->subMonths(2);

        $organization->activateLivePayments('ACCT_test1234567', $approvedAt);

        $this->assertTrue($organization->refresh()->live_payments_enabled_at->isSameDay($approvedAt));
    }

    public function test_revoking_stops_collection_but_keeps_the_subaccount_on_file(): void
    {
        $organization = $this->organization();
        $organization->activateLivePayments('ACCT_test1234567');

        $organization->revokeLivePayments();

        $organization->refresh();

        $this->assertSame(OrganizationStatus::PaymentRejected, $organization->status);
        $this->assertNull($organization->live_payments_enabled_at);
        $this->assertFalse($organization->canCollectLivePayments());
        // Kept: the owner resubmits against it, and Paystack still holds it.
        $this->assertSame('ACCT_test1234567', $organization->paystack_subaccount_code);
    }

    public function test_approving_a_profile_through_the_console_enables_collection(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();

        PaymentProfile::create([
            'organization_id' => $organization->id,
            'business_name' => 'Damaturu Mesh',
            'contact_name' => 'Adamu Bala',
            'contact_phone' => '08030000000',
            'bank_name' => 'Access Bank',
            'bank_code' => '044',
            'account_name' => 'Damaturu Mesh',
            'account_number_cipher' => '0123456789',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('platform.payment.approve', $organization), [
                'paystack_subaccount_code' => 'ACCT_test1234567',
            ])
            ->assertRedirect();

        $this->assertTrue(
            $organization->refresh()->canCollectLivePayments(),
            'Approval left the organization unable to collect — the timestamp was dropped.',
        );
    }

    public function test_the_repair_command_restores_a_dropped_timestamp(): void
    {
        $organization = $this->organization(OrganizationStatus::Live);

        // Exactly the broken shape production produced: Live, a real subaccount
        // code, an approved profile, and no activation timestamp.
        $organization->forceFill(['paystack_subaccount_code' => 'ACCT_test1234567'])->save();

        PaymentProfile::create([
            'organization_id' => $organization->id,
            'business_name' => 'Damaturu Mesh',
            'contact_name' => 'Adamu Bala',
            'contact_phone' => '08030000000',
            'bank_name' => 'Access Bank',
            'account_name' => 'Damaturu Mesh',
            'account_number_cipher' => '0123456789',
            'status' => 'approved',
            'reviewed_at' => now()->subMonth(),
            'submitted_at' => now()->subMonth(),
        ]);

        $this->assertFalse($organization->canCollectLivePayments());

        $this->artisan('hotfii:repair-live-payments')
            ->expectsConfirmation('Restore the activation timestamp on these organizations?', 'yes')
            ->assertSuccessful();

        $this->assertTrue($organization->refresh()->canCollectLivePayments());
        // Backdated to the review, not stamped today.
        $this->assertTrue($organization->live_payments_enabled_at->isSameDay(now()->subMonth()));
    }

    public function test_the_repair_command_leaves_unapproved_organizations_alone(): void
    {
        $organization = $this->organization();
        $organization->forceFill(['paystack_subaccount_code' => 'ACCT_test1234567'])->save();

        PaymentProfile::create([
            'organization_id' => $organization->id,
            'business_name' => 'Damaturu Mesh',
            'contact_name' => 'Adamu Bala',
            'contact_phone' => '08030000000',
            'bank_name' => 'Access Bank',
            'account_name' => 'Damaturu Mesh',
            'account_number_cipher' => '0123456789',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->artisan('hotfii:repair-live-payments')->assertSuccessful();

        $this->assertNull(
            $organization->refresh()->live_payments_enabled_at,
            'A profile still awaiting review must not be activated by the repair.',
        );
    }
}
