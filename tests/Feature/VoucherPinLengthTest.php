<?php

namespace Tests\Feature;

use App\Domain\Enums\VoucherPinFormat;
use App\Domain\Enums\VoucherStatus;
use App\Models\AccessPlan;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherPinGenerator;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VoucherPinLengthTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private AccessPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->organization = Organization::create([
            'name' => 'PIN Network', 'slug' => 'pin-network', 'mode' => 'commerce',
            'status' => 'trial', 'billing_plan' => 'sandbox', 'timezone' => 'Africa/Lagos',
        ]);
        $this->plan = $this->organization->accessPlans()->create([
            'name' => 'One Day', 'access_type' => 'paid', 'price_kobo' => 50000,
            'validity_days' => 1, 'simultaneous_use' => 1,
        ]);
    }

    public static function pinOptions(): iterable
    {
        foreach (VoucherPinGenerator::LENGTHS as $length) {
            foreach (VoucherPinFormat::cases() as $format) {
                foreach ([true, false] as $dashed) {
                    yield $length.'-'.$format->value.'-'.(int) $dashed => [$length, $format, $dashed];
                }
            }
        }
    }

    #[DataProvider('pinOptions')]
    public function test_all_supported_pin_settings_generate_unique_redeemable_codes(int $length, VoucherPinFormat $format, bool $dashed): void
    {
        $service = app(VoucherService::class);
        $batch = $service->createBatch($this->organization, $this->plan, 3, pinFormat: $format, dashedPin: $dashed, pinLength: $length);
        $this->assertSame($length, $batch->pin_length);
        $this->assertCount(3, $batch->vouchers->pluck('code_lookup')->unique());
        foreach ($batch->vouchers as $voucher) {
            $code = $voucher->code_cipher;
            $plain = str_replace('-', '', $code);
            $this->assertSame($length, strlen($plain));
            $this->assertMatchesRegularExpression('/^['.$format->alphabet().']+$/', $plain);
            $this->assertSame($dashed ? implode('-', str_split($plain, 4)) : $plain, $code);
        }
        $voucher = $service->redeem($this->organization, $batch->vouchers->first()->code_cipher);
        $this->assertSame(VoucherStatus::Active, $voucher->status);
    }

    public function test_short_pin_exhaustion_rolls_back_partial_batches_and_never_reuses_codes(): void
    {
        $service = app(VoucherService::class);
        $service->createBatch($this->organization, $this->plan, 90, pinLength: 2);
        try {
            $service->createBatch($this->organization, $this->plan, 20, pinLength: 2);
            $this->fail('An exhausted PIN space must reject the entire batch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pin_length', $exception->errors());
        }
        $this->assertDatabaseCount('voucher_batches', 1);
        $this->assertDatabaseCount('vouchers', 90);
        $service->createBatch($this->organization, $this->plan, 10, pinLength: 2);
        $this->assertDatabaseCount('vouchers', 100);
        $this->expectException(ValidationException::class);
        $service->createBatch($this->organization, $this->plan, 1, pinLength: 2);
    }

    public function test_batch_form_accepts_selected_length_and_rejects_invalid_or_impossible_requests(): void
    {
        $user = User::factory()->create();
        $user->organizations()->attach($this->organization, ['role' => 'owner']);
        $this->actingAs($user)->withoutVite();
        $this->get(route('vouchers.index'))->assertOk()->assertSee('PIN length');
        $data = ['access_plan_id' => $this->plan->id, 'quantity' => 1, 'pin_length' => 6, 'pin_format' => 'numbers', 'dashed_pin' => 0];
        $this->post(route('vouchers.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('vouchers.index'));
        $batch = VoucherBatch::sole();
        $this->assertSame(6, $batch->pin_length);
        $this->assertSame(6, strlen($batch->vouchers->sole()->code_cipher));
        $this->post(route('vouchers.store'), array_replace($data, ['pin_length' => 3]))->assertSessionHasErrors('pin_length');
        $this->post(route('vouchers.store'), array_replace($data, ['pin_length' => 2, 'quantity' => 101]))->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('voucher_batches', 1);
    }

    public function test_existing_callers_keep_twelve_character_pins(): void
    {
        $batch = app(VoucherService::class)->createBatch($this->organization, $this->plan, 1);
        $this->assertSame(12, $batch->pin_length);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}$/', $batch->vouchers->sole()->code_cipher);
    }
}
