<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Models\AccessPlan;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $operator;

    private NetworkDevice $mainRouter;

    private NetworkDevice $annexRouter;

    private AccessPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->organization = Organization::create([
            'name' => 'BALA STARLINK',
            'slug' => 'bala-starlink',
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Trial,
            'billing_plan' => BillingPlan::StandardSeller,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->operator = User::factory()->create();
        $this->organization->users()->attach($this->operator, ['role' => 'owner', 'joined_at' => now()]);
        $location = $this->organization->locations()->create([
            'name' => 'Main site',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->mainRouter = $this->router($location->id, 'Main router', 'online');
        $this->annexRouter = $this->router($location->id, 'Annex router', 'offline');
        $this->plan = $this->organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
        ]);
    }

    public function test_dashboard_requires_a_mobile_token_and_membership(): void
    {
        $url = route('api.v1.mobile.organizations.dashboard', $this->organization);

        $this->getJson($url)->assertUnauthorized();

        $wrongAbility = $this->operator->createToken('browser:test', ['browser'])->plainTextToken;
        $this->withToken($wrongAbility)->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();

        $outsider = User::factory()->create();
        $outsiderToken = $outsider->createToken('android:outside', ['mobile'])->plainTextToken;
        $this->withToken($outsiderToken)->getJson($url)->assertForbidden();
    }

    public function test_member_receives_real_dashboard_data_scoped_to_a_router_uuid(): void
    {
        $this->sale($this->mainRouter, 'HF-MAIN-TODAY', 500_00, now());
        $this->sale($this->mainRouter, 'HF-MAIN-YESTERDAY', 200_00, now()->subDay());
        $this->sale($this->annexRouter, 'HF-ANNEX-TODAY', 900_00, now());
        $this->organization->sessions()->create([
            'network_device_id' => $this->mainRouter->id,
            'access_plan_id' => $this->plan->id,
            'radius_username' => 'main-session',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $this->organization->sessions()->create([
            'network_device_id' => $this->annexRouter->id,
            'access_plan_id' => $this->plan->id,
            'radius_username' => 'annex-session',
            'status' => 'active',
            'started_at' => now(),
        ]);

        $token = $this->operator->createToken('android:test', ['mobile'])->plainTextToken;
        $response = $this->withToken($token)->getJson(route('api.v1.mobile.organizations.dashboard', [
            'organization' => $this->organization,
            'router' => $this->mainRouter->uuid,
        ]));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.organization.id', $this->organization->uuid)
            ->assertJsonPath('data.scope.router.id', $this->mainRouter->uuid)
            ->assertJsonCount(2, 'data.scope.routers')
            ->assertJsonPath('data.pulse.revenue_today_kobo', 500_00)
            ->assertJsonPath('data.pulse.sales_today', 1)
            ->assertJsonPath('data.pulse.active_sessions', 1)
            ->assertJsonPath('data.pulse.online_routers', 1)
            ->assertJsonPath('data.pulse.total_routers', 1)
            ->assertJsonPath('data.revenue.total_kobo', 700_00)
            ->assertJsonPath('data.top_plans.items.0.name', 'One Day')
            ->assertJsonPath('data.top_plans.items.0.revenue_kobo', 700_00)
            ->assertJsonCount(1, 'data.network_health')
            ->assertJsonPath('data.network_health.0.id', $this->mainRouter->uuid)
            ->assertJsonCount(2, 'data.recent_transactions')
            ->assertJsonMissing(['reference' => 'HF-ANNEX-TODAY']);
    }

    public function test_invalid_router_uuid_is_rejected_instead_of_broadening_the_scope(): void
    {
        $token = $this->operator->createToken('android:test', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.mobile.organizations.dashboard', [
                'organization' => $this->organization,
                'router' => (string) Str::uuid(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('router');
    }

    private function router(int $locationId, string $name, string $status): NetworkDevice
    {
        return $this->organization->networkDevices()->create([
            'location_id' => $locationId,
            'name' => $name,
            'vendor' => 'generic',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => $status,
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);
    }

    private function sale(NetworkDevice $router, string $reference, int $amountKobo, $paidAt): Transaction
    {
        $transaction = Transaction::create([
            'organization_id' => $this->organization->id,
            'network_device_id' => $router->id,
            'access_plan_id' => $this->plan->id,
            'reference' => $reference,
            'provider' => 'manual',
            'channel' => 'cash',
            'status' => PaymentStatus::Successful,
            'gross_amount_kobo' => $amountKobo,
            'platform_fee_kobo' => intdiv($amountKobo * 200, 10_000),
            'billable_sales_kobo' => $amountKobo,
            'paid_at' => $paidAt,
        ]);
        $transaction->forceFill(['created_at' => $paidAt, 'updated_at' => $paidAt])->save();

        return $transaction;
    }
}
