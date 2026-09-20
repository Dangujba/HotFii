<?php

namespace Tests\Feature;

use App\Domain\Enums\PlanValidityMode;
use App\Domain\Enums\VoucherStatus;
use App\Jobs\ExpireAccessRecords;
use App\Models\AccessCredential;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Access\AllowanceService;
use App\Services\Payments\PaymentProcessor;
use App\Services\Radius\RadiusCredentialService;
use App\Services\Vouchers\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanValidityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->organization = Organization::create([
            'name' => 'Validity Network', 'slug' => 'validity-network',
            'mode' => 'commerce', 'status' => 'trial', 'billing_plan' => 'sandbox',
            'timezone' => 'Africa/Lagos',
        ]);
        $user = User::factory()->create();
        $user->organizations()->attach($this->organization, ['role' => 'owner']);
        $this->actingAs($user)->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-09-07 23:00:00', 'Africa/Lagos'));
    }

    public function test_plan_form_defaults_to_midnight_and_accepts_rolling(): void
    {
        $data = ['name' => 'One Day', 'access_type' => 'paid', 'price_naira' => 500, 'simultaneous_use' => 1, 'validity_days' => 1];
        $this->get(route('plans.index'))->assertOk()->assertSee('Midnight (calendar days)')->assertSee('Full 24-hour days');
        $this->post(route('plans.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(PlanValidityMode::Midnight, $this->organization->accessPlans()->sole()->validity_mode);
        $this->post(route('plans.store'), array_replace($data, ['name' => 'Rolling Day', 'validity_mode' => 'rolling']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('access_plans', ['organization_id' => $this->organization->id, 'validity_mode' => 'rolling']);
        $this->post(route('plans.store'), $data + ['validity_mode' => 'invalid'])->assertSessionHasErrors('validity_mode');
        $this->assertSame(2, $this->organization->accessPlans()->count());
    }

    public function test_midnight_voucher_and_radius_expire_together(): void
    {
        $plan = $this->organization->accessPlans()->create([
            'name' => 'One Day', 'access_type' => 'paid', 'price_kobo' => 50000,
            'validity_days' => 1, 'duration_minutes' => 1440, 'simultaneous_use' => 1,
        ]);
        $service = app(VoucherService::class);
        $batch = $service->createBatch($this->organization, $plan, 1);
        $voucher = $service->redeem($this->organization, $batch->vouchers->sole()->code_cipher);
        $expected = CarbonImmutable::parse('2026-09-08 00:00:00', 'Africa/Lagos');

        $this->assertTrue($voucher->expires_at->equalTo($expected));
        $this->assertTrue($voucher->credential->expires_at->equalTo($expected));
        $this->assertDatabaseHas('radcheck', ['username' => $voucher->uuid, 'attribute' => 'Expiration', 'value' => (string) $expected->timestamp]);
        $this->assertDatabaseHas('radreply', ['username' => $voucher->uuid, 'attribute' => 'Session-Timeout', 'value' => '3600']);
        $this->assertEquals(3600, app(AllowanceService::class)->forCredential($voucher->credential)['remaining_seconds']);

        $this->travelTo($expected);
        $this->assertSame(0, app(AllowanceService::class)->forCredential($voucher->credential)['remaining_seconds']);
        app(ExpireAccessRecords::class)->handle(app(RadiusCredentialService::class));
        $this->assertSame(VoucherStatus::Expired, $voucher->refresh()->status);
        $this->assertSame('revoked', $voucher->credential->refresh()->status);
        $this->assertDatabaseMissing('radcheck', ['username' => $voucher->uuid]);
    }

    public static function saleChannels(): array
    {
        return ['online' => ['online', 'paystack'], 'cash' => ['cash', 'manual']];
    }

    #[DataProvider('saleChannels')]
    public function test_online_and_direct_cash_follow_plan_validity(string $channel, string $provider): void
    {
        $plan = $this->organization->accessPlans()->create([
            'name' => 'One Week', 'access_type' => 'paid', 'price_kobo' => 50000,
            'validity_days' => 7, 'simultaneous_use' => 1,
        ]);
        $transaction = Transaction::create([
            'organization_id' => $this->organization->id, 'access_plan_id' => $plan->id,
            'reference' => 'validity-payment', 'provider' => $provider, 'channel' => $channel,
            'status' => 'pending', 'gross_amount_kobo' => 50000, 'billable_sales_kobo' => 50000,
            'platform_fee_kobo' => 1000,
        ]);
        $processor = app(PaymentProcessor::class);
        $processor->markSuccessful($transaction, ['amount' => 50000]);
        $expiry = AccessCredential::sole()->expires_at;
        $this->assertTrue($expiry->equalTo(CarbonImmutable::parse('2026-09-14 00:00:00', 'Africa/Lagos')));
        $this->travel(1)->hours();
        $processor->markSuccessful($transaction, ['amount' => 50000]);
        $this->assertSame(1, AccessCredential::count());
        $this->assertTrue(AccessCredential::sole()->expires_at->equalTo($expiry));
    }

    public function test_usage_allowance_can_end_before_midnight(): void
    {
        $plan = $this->organization->accessPlans()->create([
            'name' => 'Half Hour', 'access_type' => 'free', 'price_kobo' => 0,
            'validity_days' => 1, 'duration_minutes' => 30, 'simultaneous_use' => 1,
        ]);
        $credential = app(RadiusCredentialService::class)->issue($this->organization, $plan);
        $this->assertDatabaseHas('radreply', ['username' => $credential->username, 'attribute' => 'Session-Timeout', 'value' => '1800']);
        $this->assertEquals(1800, app(AllowanceService::class)->forCredential($credential)['remaining_seconds']);
    }

    public function test_migration_preserves_legacy_plans_and_changes_only_the_new_default(): void
    {
        $migration = require database_path('migrations/2026_09_06_000100_add_plan_validity_mode_and_voucher_pin_length.php');
        $migration->down();
        $id = DB::table('access_plans')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organization_id' => $this->organization->id,
            'name' => 'Legacy Week', 'access_type' => 'paid', 'price_kobo' => 50000, 'validity_days' => 7,
        ]);
        $migration->up();
        $plan = $this->organization->accessPlans()->findOrFail($id);
        $this->assertSame(PlanValidityMode::Rolling, $plan->validity_mode);
        $this->assertTrue($plan->expiresAt(now(), 'Africa/Lagos')->equalTo(now()->addHours(168)));
        $newId = DB::table('access_plans')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organization_id' => $this->organization->id,
            'name' => 'New Day', 'access_type' => 'paid', 'price_kobo' => 50000, 'validity_days' => 1,
        ]);
        $this->assertDatabaseHas('access_plans', ['id' => $newId, 'validity_mode' => 'midnight']);
    }
}
