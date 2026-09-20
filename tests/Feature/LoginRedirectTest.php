<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_admin_with_no_organization_lands_on_the_platform_console(): void
    {
        User::factory()->create([
            'email' => 'admin@hotfii.test',
            'password' => 'Strong-Password-123!',
            'is_platform_admin' => true,
        ]);

        // The dashboard is behind the organization middleware, so sending a
        // support-only admin there would 403 them straight out of a fresh login.
        $this->post('/login', ['email' => 'admin@hotfii.test', 'password' => 'Strong-Password-123!'])
            ->assertRedirect(route('platform.index'));
    }

    public function test_an_admin_who_also_owns_an_organization_still_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'both@hotfii.test',
            'password' => 'Strong-Password-123!',
            'is_platform_admin' => true,
        ]);
        $this->organizationFor($user);

        $this->post('/login', ['email' => 'both@hotfii.test', 'password' => 'Strong-Password-123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_an_ordinary_operator_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'op@hotfii.test', 'password' => 'Strong-Password-123!']);
        $this->organizationFor($user);

        $this->post('/login', ['email' => 'op@hotfii.test', 'password' => 'Strong-Password-123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_two_factor_enabled_operator_must_complete_the_challenge(): void
    {
        $twoFactor = app(TwoFactorAuthentication::class);
        $secret = $twoFactor->generateSecret();
        $user = User::factory()->create([
            'email' => 'secure@hotfii.test',
            'password' => 'Strong-Password-123!',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
        $this->organizationFor($user);

        $this->post('/login', [
            'email' => 'secure@hotfii.test',
            'password' => 'Strong-Password-123!',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertSee('Two-factor verification');

        $this->post(route('two-factor.verify'), [
            'code' => $twoFactor->currentCode($secret),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    private function organizationFor(User $user): Organization
    {
        $organization = Organization::create([
            'name' => 'Kano Mesh',
            'slug' => 'kano-mesh-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
        $organization->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);

        return $organization;
    }
}
