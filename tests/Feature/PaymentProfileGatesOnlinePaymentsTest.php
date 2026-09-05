<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Location;
use App\Models\NetworkDevice;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Organizations are Live from registration, so the payment profile is the only
 * thing standing between a customer and a card charge.
 */
class PaymentProfileGatesOnlinePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'services.paystack.secret' => 'sk_test_secret',
            'services.paystack.url' => 'https://api.paystack.co',
        ]);
    }

    public function test_the_portal_refuses_card_payments_until_the_profile_is_activated(): void
    {
        [$device, $plan] = $this->deviceWithPaidPlan($this->organization());

        $this->postJson(route('portal.payment', $device), [
            'access_plan_uuid' => $plan->uuid,
            'email' => 'buyer@example.com',
        ])->assertForbidden();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_the_portal_hides_the_buy_tab_until_the_profile_is_activated(): void
    {
        [$device] = $this->deviceWithPaidPlan($this->organization());

        $response = $this->get(route('portal.show', $device));

        $response->assertOk();
        $response->assertDontSee('Pay securely with Paystack');
        // Vouchers still work, which is the point of decoupling the two.
        $response->assertSee('Activate voucher');
    }

    public function test_an_activated_profile_opens_card_payments(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ]),
        ]);

        $organization = $this->organization();
        $organization->activateLivePayments('ACCT_live123');

        [$device, $plan] = $this->deviceWithPaidPlan($organization->refresh());

        $this->postJson(route('portal.payment', $device), [
            'access_plan_uuid' => $plan->uuid,
            'email' => 'buyer@example.com',
        ])->assertOk()->assertJsonStructure(['authorization_url', 'reference']);

        $this->assertDatabaseHas('transactions', ['status' => 'pending']);
    }

    public function test_a_suspended_organization_cannot_collect_even_with_a_profile(): void
    {
        $organization = $this->organization();
        // Fully activated, then suspended: proves the suspension alone closes
        // collection, rather than the profile being incomplete.
        $organization->activateLivePayments('ACCT_live123');
        $organization->update(['status' => OrganizationStatus::Suspended]);

        [$device, $plan] = $this->deviceWithPaidPlan($organization->refresh());

        $this->postJson(route('portal.payment', $device), [
            'access_plan_uuid' => $plan->uuid,
            'email' => 'buyer@example.com',
        ])->assertForbidden();
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Kano Mesh',
            'slug' => 'kano-mesh-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
    }

    /**
     * @return array{0: NetworkDevice, 1: \App\Models\AccessPlan}
     */
    private function deviceWithPaidPlan(Organization $organization): array
    {
        $location = Location::create([
            'organization_id' => $organization->id,
            'name' => 'Sabon Gari',
            'timezone' => 'Africa/Lagos',
        ]);

        $device = NetworkDevice::create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'name' => 'Gate Router',
            'vendor' => 'generic',
            'adapter' => 'generic',
            'status' => NetworkDeviceStatus::Online,
            'nas_identifier' => 'hf-'.Str::lower(Str::random(10)),
            'radius_secret' => Str::random(24),
        ]);

        $plan = $organization->accessPlans()->create([
            'name' => 'One Hour',
            'access_type' => 'paid',
            'price_kobo' => 50000,
            'duration_minutes' => 60,
            'simultaneous_use' => 1,
        ]);

        return [$device, $plan];
    }
}
