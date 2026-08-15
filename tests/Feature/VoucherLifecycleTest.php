<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\VoucherStatus;
use App\Models\Organization;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VoucherLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_printed_voucher_activates_once_and_materializes_radius_rules(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $organization = Organization::create([
            'name' => 'Voucher Test',
            'slug' => 'voucher-test',
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Sandbox,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
        $plan = $organization->accessPlans()->create([
            'name' => 'Two Hours',
            'access_type' => 'paid',
            'price_kobo' => 50000,
            'duration_minutes' => 120,
            'simultaneous_use' => 1,
        ]);

        $batch = app(VoucherService::class)->createBatch($organization, $plan, 1);
        $code = $batch->vouchers->first()->code_cipher;
        $voucher = app(VoucherService::class)->redeem($organization, $code);

        $this->assertSame(VoucherStatus::Active, $voucher->status);
        $this->assertDatabaseHas('radcheck', ['username' => $voucher->uuid, 'attribute' => 'Cleartext-Password']);
        $this->assertDatabaseHas('radreply', ['username' => $voucher->uuid, 'attribute' => 'Session-Timeout', 'value' => '7200']);

        $this->expectException(RuntimeException::class);
        app(VoucherService::class)->redeem($organization, $code);
    }
}