<?php

namespace Tests\Feature\Operator;

use App\Models\AccessPlan;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\User;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanAndVoucherManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private NetworkDevice $router;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->organization = Organization::create([
            'name' => 'Managed Network',
            'slug' => 'managed-network',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'sandbox',
            'timezone' => 'Africa/Lagos',
        ]);

        $this->owner = User::factory()->create();
        $this->owner->organizations()->attach($this->organization, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $location = $this->organization->locations()->create(['name' => 'Main site']);
        $this->router = NetworkDevice::create([
            'organization_id' => $this->organization->id,
            'location_id' => $location->id,
            'name' => 'Main router',
            'vendor' => 'generic',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'online',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);

        $this->actingAs($this->owner)->withoutVite();
    }

    public function test_unused_plan_can_be_fully_edited_and_deleted(): void
    {
        $plan = $this->plan('Starter');

        $this->patch(route('plans.update', $plan), $this->planPayload([
            'name' => 'Starter Plus',
            'price_naira' => 750,
            'duration_minutes' => 180,
            'data_limit_mb' => 2048,
            'validity_days' => 7,
            'validity_mode' => 'rolling',
            'is_active' => 0,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Starter Plus', $plan->name);
        $this->assertSame(75000, $plan->price_kobo);
        $this->assertSame(180, $plan->duration_minutes);
        $this->assertSame(2048 * 1024 * 1024, $plan->data_limit_bytes);
        $this->assertSame('rolling', $plan->validity_mode->value);
        $this->assertFalse($plan->is_active);

        $this->delete(route('plans.destroy', $plan))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('access_plans', ['id' => $plan->id]);
    }

    public function test_used_plan_keeps_technical_definition_but_can_be_renamed_repriced_and_deactivated(): void
    {
        $plan = $this->plan('Issued plan');
        app(VoucherService::class)->createBatch($this->organization, $plan, 1);

        $this->patch(route('plans.update', $plan), $this->planPayload([
            'name' => 'Issued plan renamed',
            'price_naira' => 600,
            'is_active' => 0,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Issued plan renamed', $plan->name);
        $this->assertSame(60000, $plan->price_kobo);
        $this->assertFalse($plan->is_active);

        $this->patch(route('plans.update', $plan), $this->planPayload([
            'name' => $plan->name,
            'price_naira' => 600,
            'duration_minutes' => 30,
            'is_active' => 0,
        ]))->assertSessionHasErrors('plan');

        $this->assertSame(120, $plan->refresh()->duration_minutes);

        $this->delete(route('plans.destroy', $plan))
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseHas('access_plans', ['id' => $plan->id]);
    }

    public function test_generated_batch_can_be_edited_and_updates_every_voucher_snapshot(): void
    {
        $originalPlan = $this->plan('Original');
        $replacementPlan = $this->plan('Replacement', 60000);
        $batch = app(VoucherService::class)->createBatch($this->organization, $originalPlan, 2);

        $this->patch(route('vouchers.update', $batch), [
            'network_device_id' => $this->router->id,
            'access_plan_id' => $replacementPlan->id,
            'retail_price_naira' => 700,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame($replacementPlan->id, $batch->access_plan_id);
        $this->assertSame($this->router->id, $batch->network_device_id);
        $this->assertSame(70000, $batch->retail_price_kobo);
        $this->assertSame([70000], $batch->vouchers()->pluck('price_snapshot_kobo')->unique()->values()->all());
        $this->assertSame([$this->router->id], $batch->vouchers()->pluck('network_device_id')->unique()->values()->all());
    }

    public function test_printed_unused_batch_is_locked_for_editing_but_can_be_deleted(): void
    {
        $plan = $this->plan('Printed plan');
        $batch = app(VoucherService::class)->createBatch($this->organization, $plan, 2);
        $batch->update(['status' => 'printed', 'printed_at' => now()]);
        $batch->vouchers()->update(['status' => 'printed']);

        $this->patch(route('vouchers.update', $batch), [
            'network_device_id' => 'all',
            'access_plan_id' => $plan->id,
            'retail_price_naira' => 500,
        ])->assertSessionHasErrors('batch');

        $this->delete(route('vouchers.destroy', $batch))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('voucher_batches', ['id' => $batch->id]);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_thermal_output_renders_full_vouchers_and_marks_them_printed(): void
    {
        $plan = $this->plan('Thermal plan');
        $batch = app(VoucherService::class)->createBatch($this->organization, $plan, 2);
        $voucher = $batch->vouchers()->orderBy('id')->firstOrFail();

        $this->get(route('vouchers.thermal', ['batch' => $batch, 'width' => 88]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Thermal vouchers')
            ->assertSee('88 mm')
            ->assertSee($batch->reference)
            ->assertSee($voucher->code_cipher)
            ->assertSee($voucher->serial_number);

        $this->assertDatabaseHas('voucher_batches', ['id' => $batch->id, 'status' => 'printed']);
        $this->assertDatabaseMissing('vouchers', ['voucher_batch_id' => $batch->id, 'status' => 'generated']);

        $this->get(route('vouchers.thermal', ['batch' => $batch, 'width' => 70]))->assertNotFound();
    }

    public function test_activated_batch_and_cross_organization_records_cannot_be_changed_or_deleted(): void
    {
        $plan = $this->plan('Sold plan');
        $batch = app(VoucherService::class)->createBatch($this->organization, $plan, 1);
        app(VoucherService::class)->redeem(
            $this->organization,
            $batch->vouchers->sole()->code_cipher,
            device: $this->router,
        );

        $this->delete(route('vouchers.destroy', $batch))
            ->assertSessionHasErrors('batch');
        $this->assertDatabaseHas('voucher_batches', ['id' => $batch->id]);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('fee_ledger_entries', 1);

        $other = Organization::create([
            'name' => 'Other Network',
            'slug' => 'other-network',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'sandbox',
            'timezone' => 'Africa/Lagos',
        ]);
        $otherPlan = $other->accessPlans()->create([
            'name' => 'Other plan',
            'access_type' => 'paid',
            'price_kobo' => 50000,
            'duration_minutes' => 60,
            'simultaneous_use' => 1,
            'validity_days' => 1,
        ]);
        $otherBatch = app(VoucherService::class)->createBatch($other, $otherPlan, 1);

        $this->delete(route('plans.destroy', $otherPlan))->assertNotFound();
        $this->delete(route('vouchers.destroy', $otherBatch))->assertNotFound();
    }

    public function test_agent_cannot_edit_or_delete_plans_or_batches(): void
    {
        $plan = $this->plan('Protected plan');
        $batch = app(VoucherService::class)->createBatch($this->organization, $plan, 1);
        $agent = User::factory()->create();
        $agent->organizations()->attach($this->organization, ['role' => 'agent', 'joined_at' => now()]);
        $this->actingAs($agent);

        $this->patch(route('plans.update', $plan), $this->planPayload())->assertForbidden();
        $this->delete(route('plans.destroy', $plan))->assertForbidden();
        $this->patch(route('vouchers.update', $batch), [
            'network_device_id' => 'all',
            'access_plan_id' => $plan->id,
            'retail_price_naira' => 500,
        ])->assertForbidden();
        $this->delete(route('vouchers.destroy', $batch))->assertForbidden();
    }

    private function plan(string $name, int $priceKobo = 50000): AccessPlan
    {
        return $this->organization->accessPlans()->create([
            'name' => $name,
            'access_type' => 'paid',
            'price_kobo' => $priceKobo,
            'duration_minutes' => 120,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
        ]);
    }

    private function planPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Starter',
            'access_type' => 'paid',
            'price_naira' => 500,
            'duration_minutes' => 120,
            'data_limit_mb' => null,
            'download_kbps' => null,
            'upload_kbps' => null,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'is_active' => 1,
        ], $overrides);
    }
}
