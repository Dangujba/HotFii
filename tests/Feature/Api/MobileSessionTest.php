<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_sign_in_and_receive_organization_context(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $organization = $this->organization();
        $user->organizations()->attach($organization, ['role' => 'owner', 'joined_at' => now()]);

        $response = $this->postJson(route('api.v1.mobile.auth.login'), [
            'email' => 'owner@example.com',
            'password' => 'password',
            'device_name' => 'Muhammad Pixel',
            'device_id' => 'device-123',
        ]);

        $response->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.user.id', $user->uuid)
            ->assertJsonPath('data.organizations.0.id', $organization->uuid)
            ->assertJsonPath('data.organizations.0.role', 'owner')
            ->assertJsonPath('data.organizations.0.permissions.0', 'manage_plans')
            ->assertJsonPath('data.default_organization_id', $organization->uuid)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull($user->tokens()->sole()->expires_at);
    }

    public function test_invalid_credentials_do_not_create_a_token(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->postJson(route('api.v1.mobile.auth.login'), [
            'email' => 'owner@example.com',
            'password' => 'incorrect',
            'device_name' => 'Android',
            'device_id' => 'device-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_account_without_an_organization_cannot_open_a_mobile_session(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->postJson(route('api.v1.mobile.auth.login'), [
            'email' => 'owner@example.com',
            'password' => 'password',
            'device_name' => 'Android',
            'device_id' => 'device-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_same_device_login_replaces_its_previous_token(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $user->organizations()->attach($this->organization(), ['role' => 'manager', 'joined_at' => now()]);

        $payload = [
            'email' => 'owner@example.com',
            'password' => 'password',
            'device_name' => 'Android',
            'device_id' => 'same-device',
        ];

        $this->postJson(route('api.v1.mobile.auth.login'), $payload)->assertCreated();
        $this->postJson(route('api.v1.mobile.auth.login'), $payload)->assertCreated();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_authenticated_operator_can_restore_and_end_a_session(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization();
        $user->organizations()->attach($organization, ['role' => 'agent', 'joined_at' => now()]);
        $token = $user->createToken('android:test', ['mobile'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.mobile.session.show'))
            ->assertOk()
            ->assertJsonPath('data.organizations.0.permissions', ['create_vouchers', 'record_cash']);

        $this->withToken($token)
            ->deleteJson(route('api.v1.mobile.auth.logout'))
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson(route('api.v1.mobile.session.show'))
            ->assertUnauthorized();
    }

    public function test_operator_can_enable_two_factor_and_must_supply_a_valid_code_at_login(): void
    {
        $user = User::factory()->create(['email' => 'secure@example.com']);
        $user->organizations()->attach($this->organization(), ['role' => 'owner', 'joined_at' => now()]);
        $token = $user->createToken('android:setup', ['mobile'])->plainTextToken;
        $twoFactor = app(TwoFactorAuthentication::class);

        $setup = $this->withToken($token)
            ->postJson(route('api.v1.mobile.auth.two-factor.setup'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $secret = $setup->json('data.secret');

        $confirmation = $this->withToken($token)
            ->postJson(route('api.v1.mobile.auth.two-factor.confirm'), [
                'code' => $twoFactor->currentCode($secret),
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonCount(8, 'data.recovery_codes');

        $payload = [
            'email' => 'secure@example.com',
            'password' => 'password',
            'device_name' => 'Muhammad Pixel',
            'device_id' => 'secure-device',
        ];

        $this->postJson(route('api.v1.mobile.auth.login'), $payload)
            ->assertAccepted()
            ->assertJsonPath('two_factor_required', true);

        $this->postJson(route('api.v1.mobile.auth.login'), [
            ...$payload,
            'two_factor_code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');

        $this->postJson(route('api.v1.mobile.auth.login'), [
            ...$payload,
            'two_factor_code' => $twoFactor->currentCode($secret),
        ])->assertCreated()->assertJsonPath('data.user.two_factor_enabled', true);

        $recoveryCode = $confirmation->json('data.recovery_codes.0');
        $this->postJson(route('api.v1.mobile.auth.login'), [
            ...$payload,
            'two_factor_code' => $recoveryCode,
        ])->assertCreated();

        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'InnoBytes Network',
            'slug' => 'innobytes-network',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'sandbox',
            'timezone' => 'Africa/Lagos',
        ]);
    }
}
