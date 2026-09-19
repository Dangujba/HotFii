<?php

namespace Tests\Feature\Api;

use App\Models\AccessPlan;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileVoucherBatchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private AccessPlan $plan;

    private NetworkDevice $router;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->organization = Organization::create([
            'name' => 'BALA STARLINK',
            'slug' => 'bala-starlink',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'sandbox',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->owner = User::factory()->create();
        $this->organization->users()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
        $this->plan = $this->organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'simultaneous_use' => 1,
            'is_active' => true,
        ]);
        $location = $this->organization->locations()->create([
            'name' => 'Main site',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->router = $this->organization->networkDevices()->create([
            'location_id' => $location->id,
            'name' => 'Main router',
            'vendor' => 'generic',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'online',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);
    }

    public function test_member_can_list_batches_with_options_and_permissions(): void
    {
        $response = $this->withToken($this->token($this->owner))->getJson(
            route('api.v1.mobile.organizations.voucher-batches.index', $this->organization)
        );

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.permissions.can_create', true)
            ->assertJsonPath('data.permissions.can_manage', true)
            ->assertJsonPath('data.options.plans.0.id', $this->plan->uuid)
            ->assertJsonPath('data.options.routers.0.id', $this->router->uuid)
            ->assertJsonPath('data.options.pin_lengths', [2, 4, 6, 8, 10, 12]);
    }

    public function test_owner_can_generate_and_filter_a_router_scoped_batch(): void
    {
        $response = $this->withToken($this->token($this->owner))->postJson(
            route('api.v1.mobile.organizations.voucher-batches.store', $this->organization),
            [
                'network_device_id' => $this->router->uuid,
                'access_plan_id' => $this->plan->uuid,
                'quantity' => 3,
                'retail_price_kobo' => 600_00,
                'pin_format' => 'numbers',
                'pin_length' => 4,
                'dashed_pin' => false,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.pin_length', 4)
            ->assertJsonPath('data.retail_price_kobo', 600_00)
            ->assertJsonPath('data.router.id', $this->router->uuid)
            ->assertJsonCount(3, 'data.vouchers');

        $this->assertDatabaseCount('vouchers', 3);
        $this->withToken($this->token($this->owner))
            ->getJson(route('api.v1.mobile.organizations.voucher-batches.index', [
                'organization' => $this->organization,
                'router' => $this->router->uuid,
                'search' => substr($response->json('data.reference'), -4),
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.batches');
    }

    public function test_sharing_returns_codes_and_prevents_later_repricing(): void
    {
        $batch = $this->batch();
        $share = $this->withToken($this->token($this->owner))->postJson(
            route('api.v1.mobile.organizations.voucher-batches.share', [
                'organization' => $this->organization,
                'batch' => $batch,
            ])
        );

        $share->assertOk()
            ->assertJsonPath('data.reference', $batch->reference)
            ->assertJsonCount(2, 'data.codes')
            ->assertJsonStructure(['data' => ['codes' => [['id', 'serial_number', 'code']]]]);
        $this->assertDatabaseHas('voucher_batches', ['id' => $batch->id, 'status' => 'printed']);
        $this->assertDatabaseMissing('vouchers', ['voucher_batch_id' => $batch->id, 'status' => 'generated']);

        $this->withToken($this->token($this->owner))->patchJson(
            route('api.v1.mobile.organizations.voucher-batches.update', [
                'organization' => $this->organization,
                'batch' => $batch,
            ]),
            [
                'network_device_id' => 'all',
                'access_plan_id' => $this->plan->uuid,
                'retail_price_kobo' => 700_00,
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('batch');
    }

    public function test_agent_can_generate_but_cannot_edit_or_delete(): void
    {
        $agent = User::factory()->create();
        $this->organization->users()->attach($agent, ['role' => 'agent', 'joined_at' => now()]);
        $batch = $this->batch();

        $this->withToken($this->token($agent))->patchJson(
            route('api.v1.mobile.organizations.voucher-batches.update', [
                'organization' => $this->organization,
                'batch' => $batch,
            ]),
            [
                'network_device_id' => 'all',
                'access_plan_id' => $this->plan->uuid,
                'retail_price_kobo' => 500_00,
            ],
        )->assertForbidden();

        $this->withToken($this->token($agent))->deleteJson(
            route('api.v1.mobile.organizations.voucher-batches.destroy', [
                'organization' => $this->organization,
                'batch' => $batch,
            ])
        )->assertForbidden();
    }

    public function test_used_batch_cannot_be_deleted(): void
    {
        $batch = $this->batch();
        $batch->vouchers()->first()->update(['status' => 'sold', 'sold_at' => now()]);

        $this->withToken($this->token($this->owner))->deleteJson(
            route('api.v1.mobile.organizations.voucher-batches.destroy', [
                'organization' => $this->organization,
                'batch' => $batch,
            ])
        )->assertUnprocessable()->assertJsonValidationErrors('batch');

        $this->assertDatabaseHas('voucher_batches', ['id' => $batch->id]);
    }

    private function batch(): VoucherBatch
    {
        return app(VoucherService::class)->createBatch(
            $this->organization,
            $this->plan,
            2,
            500_00,
            device: $this->router,
        );
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(8), ['mobile'])->plainTextToken;
    }
}
