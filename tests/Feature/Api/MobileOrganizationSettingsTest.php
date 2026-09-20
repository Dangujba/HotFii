<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileOrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_manager_can_update_organization_settings_but_agent_cannot(): void
    {
        $organization = $this->organization();
        $owner = $this->member($organization, 'owner');
        $manager = $this->member($organization, 'manager');
        $agent = $this->member($organization, 'agent');

        $this->withToken($this->token($owner))->getJson(route(
            'api.v1.mobile.organizations.settings.show',
            $organization,
        ))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.organization.name', 'BALA STARLINK')
            ->assertJsonPath('data.permissions.can_manage_organization', true)
            ->assertJsonPath('data.permissions.can_manage_payment_profile', true);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($manager))->patchJson(route(
            'api.v1.mobile.organizations.settings.update',
            $organization,
        ), [
            'name' => 'BALA STARLINK KANO',
            'timezone' => 'Africa/Lagos',
            'portal_name' => 'Bala Wi-Fi',
            'portal_primary_color' => '#ff6600',
        ])->assertOk()->assertJsonPath('data.organization.portal_name', 'Bala Wi-Fi');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'user_id' => $manager->id,
            'action' => 'organization.settings.updated',
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($agent))->patchJson(route(
            'api.v1.mobile.organizations.settings.update',
            $organization,
        ), [
            'name' => 'Forbidden',
            'timezone' => 'Africa/Lagos',
        ])->assertForbidden();
    }

    public function test_only_owner_can_submit_payment_profile_and_secrets_are_masked(): void
    {
        config()->set('services.paystack.secret', null);
        $organization = $this->organization();
        $owner = $this->member($organization, 'owner');
        $manager = $this->member($organization, 'manager');
        $payload = [
            'business_name' => 'Bala Starlink',
            'contact_name' => 'Bala Musa',
            'contact_phone' => '08030000000',
            'bank_name' => 'Access Bank',
            'account_name' => 'Bala Musa',
            'account_number' => '0123456789',
            'identity_type' => 'nin',
            'identity_number' => '12345678901',
        ];

        $this->withToken($this->token($manager))->postJson(route(
            'api.v1.mobile.organizations.settings.payment-profile',
            $organization,
        ), $payload)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($owner))->postJson(route(
            'api.v1.mobile.organizations.settings.payment-profile',
            $organization,
        ), $payload)->assertOk()
            ->assertJsonPath('data.payment_profile.status', 'submitted')
            ->assertJsonPath('data.payment_profile.account_number_hint', '••••••6789')
            ->assertJsonPath('data.payment_profile.identity_number_hint', '•••••••8901')
            ->assertJsonMissing(['account_number' => '0123456789'])
            ->assertJsonMissing(['identity_number' => '12345678901']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($manager))->getJson(route(
            'api.v1.mobile.organizations.settings.show',
            $organization,
        ))->assertOk()
            ->assertJsonPath('data.payment_profile', null)
            ->assertJsonPath('data.permissions.can_manage_payment_profile', false);
    }

    public function test_team_rules_prevent_manager_escalation_and_keep_a_last_owner(): void
    {
        $organization = $this->organization();
        $owner = $this->member($organization, 'owner');
        $manager = $this->member($organization, 'manager');
        $newUser = User::factory()->create(['email' => 'new@example.com']);

        $this->withToken($this->token($manager))->postJson(route(
            'api.v1.mobile.organizations.team.store',
            $organization,
        ), ['email' => $newUser->email, 'role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->withToken($this->token($manager))->postJson(route(
            'api.v1.mobile.organizations.team.store',
            $organization,
        ), ['email' => $newUser->email, 'role' => 'agent'])
            ->assertCreated()
            ->assertJsonPath('data.member.role', 'agent');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($owner))->patchJson(route(
            'api.v1.mobile.organizations.team.update',
            ['organization' => $organization, 'member' => $owner],
        ), ['role' => 'viewer'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The organization must keep at least one owner.');

        $this->withToken($this->token($owner))->getJson(route(
            'api.v1.mobile.organizations.team.index',
            $organization,
        ))->assertOk()
            ->assertJsonPath('data.permissions.can_change_roles', true)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_user_can_list_and_revoke_another_mobile_device_session(): void
    {
        $organization = $this->organization();
        $user = $this->member($organization, 'owner');
        $other = $user->createToken('android:Samsung A15:1111111111111111', ['mobile']);
        $current = $user->createToken('android:Google Pixel:2222222222222222', ['mobile']);

        $response = $this->withToken($current->plainTextToken)
            ->getJson(route('api.v1.mobile.auth.sessions.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data.sessions');

        $otherSession = collect($response->json('data.sessions'))->firstWhere('is_current', false);
        $this->assertNotNull($otherSession);

        $this->withToken($current->plainTextToken)->deleteJson(route(
            'api.v1.mobile.auth.sessions.destroy',
            ['session' => $otherSession['id']],
        ))->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'BALA STARLINK',
            'slug' => 'bala-starlink-'.Str::lower(Str::random(5)),
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'micro_seller',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }

    private function member(Organization $organization, string $role): User
    {
        $user = User::factory()->create();
        $organization->users()->attach($user, ['role' => $role, 'joined_at' => now()]);

        return $user;
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(16), ['mobile'])->plainTextToken;
    }
}
