<?php

namespace Tests\Feature\Api;

use App\Models\AccessPlan;
use App\Models\Customer;
use App\Models\FeeLedgerEntry;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileSalesAndCustomerTest extends TestCase
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
            'name' => 'Mobile Seller',
            'slug' => 'mobile-seller',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'micro_seller',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->organization->forceFill([
            'trial_started_at' => now()->subDay(),
            'trial_ends_at' => now()->addDays(13),
        ])->save();
        $this->owner = User::factory()->create();
        $this->organization->users()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
        $location = $this->organization->locations()->create(['name' => 'Main site', 'timezone' => 'Africa/Lagos']);
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
        $this->plan = $this->organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'is_active' => true,
        ]);
    }

    public function test_sales_catalog_separates_online_voucher_and_direct_cash_and_counts_activation_once(): void
    {
        $this->transaction('HF-ONLINE-ONE', 'online', 1000_00);
        $this->transaction('HF-CASH-ONE', 'cash', 750_00);
        $vouchers = app(VoucherService::class);
        $batch = $vouchers->createBatch($this->organization, $this->plan, 1, device: $this->router);
        $vouchers->redeem(
            $this->organization,
            $batch->vouchers->first()->code_cipher,
            '08030000000',
            $this->router,
        );

        $response = $this->withToken($this->token($this->owner))
            ->getJson(route('api.v1.mobile.organizations.sales.index', [
                'organization' => $this->organization,
                'router' => $this->router->uuid,
            ]));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.summary.online_sales_kobo', 1000_00)
            ->assertJsonPath('data.summary.printed_voucher_sales_kobo', 500_00)
            ->assertJsonPath('data.summary.direct_cash_sales_kobo', 750_00)
            ->assertJsonPath('data.summary.total_sales_kobo', 2250_00)
            ->assertJsonPath('data.summary.voucher_activations_count', 1)
            ->assertJsonCount(3, 'data.transactions')
            ->assertJsonCount(1, 'data.voucher_activations')
            ->assertJsonFragment(['sale_type' => 'voucher', 'gross_amount_kobo' => 500_00]);

        $this->assertSame(1, Transaction::where('reference', 'like', 'HF-VCH-%')->count());
        $this->assertSame(1, FeeLedgerEntry::where('source_type', 'voucher')->count());
    }

    public function test_channel_filter_does_not_mix_printed_voucher_and_direct_cash(): void
    {
        $this->transaction('HF-CASH-ONE', 'cash', 750_00);
        $vouchers = app(VoucherService::class);
        $batch = $vouchers->createBatch($this->organization, $this->plan, 1, device: $this->router);
        $vouchers->redeem($this->organization, $batch->vouchers->first()->code_cipher, null, $this->router);

        $voucher = $this->withToken($this->token($this->owner))->getJson(route(
            'api.v1.mobile.organizations.sales.index',
            ['organization' => $this->organization, 'channel' => 'voucher'],
        ));
        $voucher->assertOk()
            ->assertJsonPath('data.transactions_pagination.total', 1)
            ->assertJsonPath('data.transactions.0.sale_type', 'voucher');

        $cash = $this->withToken($this->token($this->owner))->getJson(route(
            'api.v1.mobile.organizations.sales.index',
            ['organization' => $this->organization, 'channel' => 'cash'],
        ));
        $cash->assertOk()
            ->assertJsonPath('data.transactions_pagination.total', 1)
            ->assertJsonPath('data.transactions.0.sale_type', 'cash');
    }

    public function test_owner_can_record_cash_and_receives_one_time_credentials_and_one_accrued_fee(): void
    {
        $token = $this->token($this->owner);
        $payload = [
            'request_id' => (string) Str::uuid(),
            'access_plan_id' => $this->plan->uuid,
            'network_device_id' => $this->router->uuid,
            'customer_name' => 'Aisha',
            'phone' => '08035550123',
        ];
        $response = $this->withToken($token)
            ->postJson(route('api.v1.mobile.organizations.sales.cash.store', $this->organization), $payload);

        $response->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.transaction.sale_type', 'cash')
            ->assertJsonPath('data.transaction.status', 'successful')
            ->assertJsonPath('data.transaction.gross_amount_kobo', 500_00)
            ->assertJsonPath('data.transaction.platform_fee_kobo', 1000)
            ->assertJsonPath('data.transaction.customer_name', 'Aisha')
            ->assertJsonStructure(['data' => ['credential' => ['username', 'password']]]);

        $this->assertDatabaseHas('transactions', [
            'organization_id' => $this->organization->id,
            'channel' => 'cash',
            'status' => 'successful',
            'gross_amount_kobo' => 500_00,
        ]);
        $this->assertDatabaseHas('fee_ledger_entries', [
            'organization_id' => $this->organization->id,
            'source_type' => 'transaction',
            'fee_amount_kobo' => 1000,
            'status' => 'accrued',
        ]);
        $this->assertSame(1, FeeLedgerEntry::where('organization_id', $this->organization->id)->count());

        $this->withToken($token)
            ->postJson(route('api.v1.mobile.organizations.sales.cash.store', $this->organization), $payload)
            ->assertOk()
            ->assertJsonPath('data.transaction.id', $response->json('data.transaction.id'))
            ->assertJsonPath('data.credential.username', $response->json('data.credential.username'));
        $this->assertSame(1, Transaction::where('organization_id', $this->organization->id)->count());
        $this->assertSame(1, FeeLedgerEntry::where('organization_id', $this->organization->id)->count());
    }

    public function test_customer_list_and_detail_are_paginated_and_tenant_isolated(): void
    {
        $customer = $this->organization->customers()->create([
            'name' => 'Musa Ibrahim',
            'phone' => '08031110000',
            'type' => 'customer',
            'status' => 'active',
        ]);
        $this->transaction('HF-CASH-MUSA', 'cash', 500_00, $customer);
        $this->organization->sessions()->create([
            'network_device_id' => $this->router->id,
            'customer_id' => $customer->id,
            'access_plan_id' => $this->plan->id,
            'radius_username' => 'hf-musa',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $other = Organization::create([
            'name' => 'Other Seller',
            'slug' => 'other-seller',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'micro_seller',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $outsider = $other->customers()->create(['name' => 'Hidden', 'type' => 'customer', 'status' => 'active']);

        $list = $this->withToken($this->token($this->owner))
            ->getJson(route('api.v1.mobile.organizations.customers.index', [
                'organization' => $this->organization,
                'search' => 'Musa',
            ]));
        $list->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.customers.0.name', 'Musa Ibrahim')
            ->assertJsonPath('data.customers.0.sessions_count', 1)
            ->assertJsonPath('data.customers.0.transactions_count', 1);

        $detail = $this->withToken($this->token($this->owner))
            ->getJson(route('api.v1.mobile.organizations.customers.show', [
                'organization' => $this->organization,
                'customer' => $customer,
            ]));
        $detail->assertOk()
            ->assertJsonPath('data.customer.name', 'Musa Ibrahim')
            ->assertJsonCount(1, 'data.recent_sessions')
            ->assertJsonCount(1, 'data.recent_transactions');

        $this->withToken($this->token($this->owner))
            ->getJson(route('api.v1.mobile.organizations.customers.show', [
                'organization' => $this->organization,
                'customer' => $outsider,
            ]))
            ->assertNotFound();
    }

    public function test_viewer_can_read_sales_but_cannot_record_cash(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer', 'joined_at' => now()]);

        $this->withToken($this->token($viewer))
            ->getJson(route('api.v1.mobile.organizations.sales.index', $this->organization))
            ->assertOk()
            ->assertJsonPath('data.permissions.can_record_cash', false);
        $this->withToken($this->token($viewer))
            ->postJson(route('api.v1.mobile.organizations.sales.cash.store', $this->organization), [
                'request_id' => (string) Str::uuid(),
                'access_plan_id' => $this->plan->uuid,
                'network_device_id' => $this->router->uuid,
            ])
            ->assertForbidden();
    }

    private function transaction(string $reference, string $channel, int $amount, ?Customer $customer = null): Transaction
    {
        return Transaction::create([
            'organization_id' => $this->organization->id,
            'network_device_id' => $this->router->id,
            'customer_id' => $customer?->id,
            'access_plan_id' => $this->plan->id,
            'reference' => $reference,
            'provider' => $channel === 'online' ? 'paystack' : 'manual',
            'channel' => $channel,
            'status' => 'successful',
            'gross_amount_kobo' => $amount,
            'platform_fee_kobo' => intdiv($amount * 200, 10_000),
            'billable_sales_kobo' => $amount,
            'paid_at' => now(),
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(8), ['mobile'])->plainTextToken;
    }
}
