<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_immediately_live_tenant(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ada Operator',
            'email' => 'ada@example.test',
            'organization_name' => 'Ada Market Wi-Fi',
            'mode' => 'commerce',
            'password' => 'Strong-Password-123!',
            'password_confirmation' => 'Strong-Password-123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        // Live from registration, but with no settlement account, so the
        // organization is operational without being able to collect money.
        $this->assertDatabaseHas('organizations', [
            'name' => 'Ada Market Wi-Fi',
            'status' => OrganizationStatus::Live->value,
            'billing_plan' => BillingPlan::Sandbox->value,
            'live_payments_enabled_at' => null,
            'paystack_subaccount_code' => null,
            'trial_started_at' => null,
        ]);
        $this->assertDatabaseHas('organization_user', ['role' => 'owner']);
        $this->assertFalse(Organization::where('name', 'Ada Market Wi-Fi')->sole()->canCollectLivePayments());
    }
}