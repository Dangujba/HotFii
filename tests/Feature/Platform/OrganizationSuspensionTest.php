<?php

namespace Tests\Feature\Platform;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Suspension is the only write the console gained.
 *
 * It stops a paying business from selling, so the two things that matter are
 * that the reason is recorded and that reactivation puts the account back where
 * it belongs rather than somewhere convenient.
 */
class OrganizationSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspending_records_the_status_and_the_reason(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'suspend',
                'reason' => 'Chargebacks on every sale since Tuesday.',
            ])
            ->assertRedirect();

        $this->assertSame(OrganizationStatus::Suspended, $organization->fresh()->status);

        $log = AuditLog::where('organization_id', $organization->id)->latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('organization.suspended', $log->action);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Chargebacks on every sale since Tuesday.', $log->reason);
        $this->assertSame(['status' => 'sandbox'], $log->before);
        $this->assertSame(['status' => 'suspended'], $log->after);
    }

    public function test_a_reason_shorter_than_ten_characters_is_refused(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'suspend',
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(OrganizationStatus::Sandbox, $organization->fresh()->status);
        $this->assertSame(0, AuditLog::where('organization_id', $organization->id)->count());
    }

    /**
     * Nothing records the status an account held before suspension, so
     * reactivation derives it. An approved settlement subaccount means the
     * account was collecting real money and goes back to Live.
     */
    public function test_reactivating_an_account_that_can_collect_restores_live(): void
    {
        $organization = $this->organization(OrganizationStatus::Suspended);
        $organization->forceFill([
            'paystack_subaccount_code' => 'ACCT_test1234567',
            'live_payments_enabled_at' => now()->subMonth(),
        ])->save();

        $this->reactivate($organization);

        $this->assertSame(OrganizationStatus::Live, $organization->fresh()->status);
    }

    public function test_reactivating_a_trial_account_restores_trial(): void
    {
        $organization = $this->organization(OrganizationStatus::Suspended);
        $organization->forceFill([
            'trial_started_at' => now()->subDays(3),
            'trial_ends_at' => now()->addDays(11),
        ])->save();

        $this->reactivate($organization);

        $this->assertSame(OrganizationStatus::Trial, $organization->fresh()->status);
    }

    public function test_reactivating_an_untouched_account_restores_sandbox(): void
    {
        $organization = $this->organization(OrganizationStatus::Suspended);

        $this->reactivate($organization);

        $this->assertSame(OrganizationStatus::Sandbox, $organization->fresh()->status);

        $log = AuditLog::where('organization_id', $organization->id)->latest()->first();

        $this->assertSame('organization.reactivated', $log->action);
        $this->assertSame(['status' => 'suspended'], $log->before);
        $this->assertSame(['status' => 'sandbox'], $log->after);
    }

    /**
     * A no-op would otherwise write an audit row recording no change, which is
     * worse than nothing: it reads like something happened.
     */
    public function test_suspending_an_already_suspended_account_is_refused(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization(OrganizationStatus::Suspended);

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'suspend',
                'reason' => 'Suspending an account that is already suspended.',
            ])
            ->assertStatus(422);

        $this->assertSame(0, AuditLog::where('organization_id', $organization->id)->count());
    }

    public function test_reactivating_an_account_that_is_not_suspended_is_refused(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'reactivate',
                'reason' => 'Reactivating an account that never stopped.',
            ])
            ->assertStatus(422);

        $this->assertSame(OrganizationStatus::Sandbox, $organization->fresh()->status);
    }

    public function test_an_operator_cannot_suspend_anybody(): void
    {
        $operator = User::factory()->create();
        $organization = $this->organization();
        $organization->users()->attach($operator, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($operator)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'suspend',
                'reason' => 'Trying to suspend my own competitor.',
            ])
            ->assertForbidden();

        $this->assertSame(OrganizationStatus::Sandbox, $organization->fresh()->status);
    }

    /**
     * A deleted account is read-only: the show route resolves it so its money
     * and audit trail stay readable, but no write route does.
     */
    public function test_a_deleted_organization_cannot_be_suspended(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();
        $organization->delete();

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'suspend',
                'reason' => 'Suspending an account that is already gone.',
            ])
            ->assertNotFound();
    }

    private function reactivate(Organization $organization): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('platform.organizations.status', $organization), [
                'action' => 'reactivate',
                'reason' => 'Dispute settled, they can sell again.',
            ])
            ->assertRedirect();
    }

    private function organization(OrganizationStatus $status = OrganizationStatus::Sandbox): Organization
    {
        return Organization::create([
            'name' => 'Damaturu Mesh',
            'slug' => 'damaturu-mesh-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => $status,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
    }
}
