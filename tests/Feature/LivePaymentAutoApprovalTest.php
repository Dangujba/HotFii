<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LivePaymentAutoApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.paystack.secret' => 'sk_live_secret', 'services.paystack.url' => 'https://api.paystack.co']);
    }

    public function test_profile_is_approved_and_organization_goes_live_when_paystack_confirms_the_account(): void
    {
        Http::fake([
            'api.paystack.co/bank?*' => Http::response($this->bankListResponse()),
            'api.paystack.co/bank/resolve*' => Http::response($this->resolveResponse('BABA GONI MUHAMMAD')),
            'api.paystack.co/subaccount' => Http::response([
                'status' => true,
                'data' => ['subaccount_code' => 'ACCT_auto123'],
            ]),
        ]);

        [$user, $organization] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), $this->payload())
            ->assertRedirect();

        $organization->refresh();

        $this->assertSame(OrganizationStatus::Live, $organization->status);
        $this->assertSame('ACCT_auto123', $organization->paystack_subaccount_code);
        $this->assertNotNull($organization->live_payments_enabled_at);
        $this->assertTrue($organization->canCollectLivePayments());

        $profile = $organization->paymentProfile->refresh();
        $this->assertSame('approved', $profile->status);
        $this->assertNotNull($profile->auto_approved_at);
        $this->assertSame('057', $profile->bank_code);
        $this->assertSame('BABA GONI MUHAMMAD', $profile->resolved_account_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment-profile.auto-approved']);

        // The subaccount is labelled with the settlement account's name, in the
        // bank's spelling, so Paystack's dashboard shows who is being paid
        // rather than the organization's name.
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/subaccount'
            && $request['business_name'] === 'BABA GONI MUHAMMAD');
    }

    public function test_a_resubmitted_profile_keeps_the_stored_account_and_identity_numbers(): void
    {
        Http::fake([
            'api.paystack.co/bank?*' => Http::response($this->bankListResponse()),
            'api.paystack.co/bank/resolve*' => Http::response($this->resolveResponse('BABA GONI MUHAMMAD')),
            'api.paystack.co/subaccount' => Http::response([
                'status' => true,
                'data' => ['subaccount_code' => 'ACCT_auto123'],
            ]),
            'api.paystack.co/subaccount/*' => Http::response([
                'status' => true,
                'data' => ['subaccount_code' => 'ACCT_auto123'],
            ]),
        ]);

        [$user, $organization] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), $this->payload())
            ->assertRedirect();

        // The form never renders the ciphers back, so an empty box on a
        // resubmit has to keep what is on file rather than wipe it.
        $resubmit = $this->payload();
        unset($resubmit['account_number'], $resubmit['identity_number']);
        $resubmit['contact_phone'] = '08039999999';

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), $resubmit)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = $organization->paymentProfile()->first();

        $this->assertSame('0123456789', $profile->account_number_cipher);
        $this->assertSame('12345678901', $profile->identity_number_cipher);
        $this->assertSame('08039999999', $profile->contact_phone);
    }

    public function test_name_mismatch_falls_back_to_manual_review(): void
    {
        Http::fake([
            'api.paystack.co/bank?*' => Http::response($this->bankListResponse()),
            'api.paystack.co/bank/resolve*' => Http::response($this->resolveResponse('SOMEONE ELSE ENTIRELY')),
            'api.paystack.co/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_should_not_be_used']]),
        ]);

        [$user, $organization] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), $this->payload())
            ->assertRedirect();

        $organization->refresh();

        // Submitting bank details does not take the account offline — see
        // SettingsController::submitPaymentProfile. The organization carries on
        // exactly as it was; it is the profile that holds the review state, and
        // the missing subaccount that keeps card payments shut.
        $this->assertSame(OrganizationStatus::Sandbox, $organization->status);
        $this->assertNull($organization->paystack_subaccount_code);
        $this->assertFalse($organization->canCollectLivePayments());

        $profile = $organization->paymentProfile->refresh();
        $this->assertSame('submitted', $profile->status);
        $this->assertNull($profile->auto_approved_at);
        $this->assertStringContainsString('SOMEONE ELSE ENTIRELY', (string) $profile->review_notes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment-profile.auto-approval-declined']);
    }

    public function test_unresolvable_account_under_live_keys_falls_back_to_manual_review(): void
    {
        Http::fake([
            'api.paystack.co/bank?*' => Http::response($this->bankListResponse()),
            'api.paystack.co/bank/resolve*' => Http::response(['status' => false, 'message' => 'Could not resolve account name.'], 422),
            'api.paystack.co/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_should_not_be_used']]),
        ]);

        [$user, $organization] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), $this->payload())
            ->assertRedirect();

        $organization->refresh();

        $this->assertSame(OrganizationStatus::Sandbox, $organization->status);
        $this->assertNull($organization->paystack_subaccount_code);
        $this->assertSame('submitted', $organization->paymentProfile->refresh()->status);
    }

    public function test_test_mode_approves_without_resolving_the_account(): void
    {
        config(['services.paystack.secret' => 'sk_test_secret']);

        Http::fake([
            'api.paystack.co/bank?*' => Http::response($this->bankListResponse()),
            'api.paystack.co/bank/resolve*' => Http::response(['status' => false, 'message' => 'Test mode cannot resolve this account.'], 422),
            'api.paystack.co/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_test123']]),
        ]);

        [$user, $organization] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), $this->payload())
            ->assertRedirect();

        $organization->refresh();

        $this->assertSame(OrganizationStatus::Live, $organization->status);
        $this->assertSame('ACCT_test123', $organization->paystack_subaccount_code);
        $this->assertNotNull($organization->paymentProfile->refresh()->auto_approved_at);
    }

    public function test_a_bank_code_outside_the_paystack_list_is_rejected(): void
    {
        Http::fake([
            'api.paystack.co/bank?*' => Http::response($this->bankListResponse()),
        ]);

        [$user, $organization] = $this->owner();

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->post(route('settings.payment-profile'), ['bank_code' => '999'] + $this->payload())
            ->assertSessionHasErrors('bank_code');

        $this->assertSame(OrganizationStatus::Sandbox, $organization->refresh()->status);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function owner(): array
    {
        $user = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Kano Mesh',
            'slug' => 'kano-mesh-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Sandbox,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
        $organization->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);

        return [$user, $organization];
    }

    private function payload(): array
    {
        return [
            'business_name' => 'Kano Mesh Ltd',
            'contact_name' => 'Baba Goni',
            'contact_phone' => '08030000000',
            'bank_code' => '057',
            'account_name' => 'Baba Goni Muhammad',
            'account_number' => '0123456789',
            'identity_type' => 'nin',
            'identity_number' => '12345678901',
        ];
    }

    private function bankListResponse(): array
    {
        return [
            'status' => true,
            'data' => [
                ['name' => 'Zenith Bank', 'code' => '057', 'slug' => 'zenith-bank'],
                ['name' => 'Access Bank', 'code' => '044', 'slug' => 'access-bank'],
            ],
        ];
    }

    private function resolveResponse(string $accountName): array
    {
        return [
            'status' => true,
            'data' => ['account_number' => '0123456789', 'account_name' => $accountName],
        ];
    }
}
