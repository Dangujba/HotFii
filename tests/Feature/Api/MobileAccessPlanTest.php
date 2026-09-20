<?php

namespace Tests\Feature\Api;

use App\Models\AccessPlan;
use App\Models\Organization;
use App\Models\User;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileAccessPlanTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->organization = Organization::create([
            'name' => 'Mobile Plans',
            'slug' => 'mobile-plans',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'sandbox',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->owner = User::factory()->create();
        $this->organization->users()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
    }

    public function test_owner_can_list_filter_and_create_midnight_plan(): void
    {
        $this->plan('Existing');

        $this->withToken($this->token($this->owner))
            ->getJson(route('api.v1.mobile.organizations.plans.index', [
                'organization' => $this->organization,
                'search' => 'Exist',
                'type' => 'paid',
                'state' => 'active',
            ]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.permissions.can_manage', true)
            ->assertJsonPath('data.options.timezone', 'Africa/Lagos')
            ->assertJsonPath('data.options.validity_modes.0.value', 'midnight')
            ->assertJsonCount(1, 'data.plans');

        $response = $this->withToken($this->token($this->owner))
            ->postJson(route('api.v1.mobile.organizations.plans.store', $this->organization), [
                'name' => 'Seven Days',
                'access_type' => 'paid',
                'price_kobo' => 1500_00,
                'duration_minutes' => 10080,
                'data_limit_mb' => 5120,
                'download_kbps' => 20000,
                'upload_kbps' => 5000,
                'simultaneous_use' => 2,
                'validity_days' => 7,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Seven Days')
            ->assertJsonPath('data.validity_mode', 'midnight')
            ->assertJsonPath('data.validity_label', '7 calendar days, expires at midnight')
            ->assertJsonPath('data.data_limit_mb', 5120)
            ->assertJsonPath('data.can_delete', true);
        $this->assertDatabaseHas('access_plans', [
            'organization_id' => $this->organization->id,
            'name' => 'Seven Days',
            'price_kobo' => 1500_00,
            'data_limit_bytes' => 5120 * 1024 * 1024,
            'validity_mode' => 'midnight',
        ]);
    }

    public function test_unused_plan_can_be_edited_and_deleted(): void
    {
        $plan = $this->plan('Starter');
        $payload = $this->payload([
            'name' => 'Starter Plus',
            'price_kobo' => 750_00,
            'duration_minutes' => 180,
            'validity_days' => 7,
            'validity_mode' => 'rolling',
            'is_active' => false,
        ]);

        $this->withToken($this->token($this->owner))
            ->patchJson(route('api.v1.mobile.organizations.plans.update', [
                'organization' => $this->organization,
                'plan' => $plan,
            ]), $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Starter Plus')
            ->assertJsonPath('data.validity_label', '168 hours from activation')
            ->assertJsonPath('data.is_active', false);

        $this->withToken($this->token($this->owner))
            ->deleteJson(route('api.v1.mobile.organizations.plans.destroy', [
                'organization' => $this->organization,
                'plan' => $plan,
            ]))
            ->assertNoContent();
        $this->assertDatabaseMissing('access_plans', ['id' => $plan->id]);
    }

    public function test_used_plan_allows_name_price_and_state_but_locks_technical_definition(): void
    {
        $plan = $this->plan('Issued');
        app(VoucherService::class)->createBatch($this->organization, $plan, 1);

        $this->withToken($this->token($this->owner))
            ->patchJson(route('api.v1.mobile.organizations.plans.update', [
                'organization' => $this->organization,
                'plan' => $plan,
            ]), $this->payload([
                'name' => 'Issued Renamed',
                'price_kobo' => 600_00,
                'is_active' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('data.is_used', true)
            ->assertJsonPath('data.can_delete', false);

        $this->withToken($this->token($this->owner))
            ->patchJson(route('api.v1.mobile.organizations.plans.update', [
                'organization' => $this->organization,
                'plan' => $plan,
            ]), $this->payload([
                'name' => 'Issued Renamed',
                'price_kobo' => 600_00,
                'duration_minutes' => 30,
                'is_active' => false,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');

        $this->withToken($this->token($this->owner))
            ->deleteJson(route('api.v1.mobile.organizations.plans.destroy', [
                'organization' => $this->organization,
                'plan' => $plan,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    public function test_agent_can_view_but_cannot_manage_plans(): void
    {
        $agent = User::factory()->create();
        $this->organization->users()->attach($agent, ['role' => 'agent', 'joined_at' => now()]);
        $plan = $this->plan('Protected');

        $this->withToken($this->token($agent))
            ->getJson(route('api.v1.mobile.organizations.plans.index', $this->organization))
            ->assertOk()
            ->assertJsonPath('data.permissions.can_manage', false)
            ->assertJsonPath('data.plans.0.can_edit', false);

        $this->withToken($this->token($agent))
            ->postJson(route('api.v1.mobile.organizations.plans.store', $this->organization), $this->payload())
            ->assertForbidden();
        $this->withToken($this->token($agent))
            ->deleteJson(route('api.v1.mobile.organizations.plans.destroy', [
                'organization' => $this->organization,
                'plan' => $plan,
            ]))
            ->assertForbidden();
    }

    private function plan(string $name): AccessPlan
    {
        return $this->organization->accessPlans()->create([
            'name' => $name,
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 120,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Starter',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 120,
            'data_limit_mb' => null,
            'download_kbps' => null,
            'upload_kbps' => null,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'is_active' => true,
        ], $overrides);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(8), ['mobile'])->plainTextToken;
    }
}
