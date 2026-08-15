<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_immediate_tenant_sandbox(): void
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
        $this->assertDatabaseHas('organizations', [
            'name' => 'Ada Market Wi-Fi',
            'status' => OrganizationStatus::Sandbox->value,
            'billing_plan' => BillingPlan::Sandbox->value,
        ]);
        $this->assertDatabaseHas('organization_user', ['role' => 'owner']);
    }
}