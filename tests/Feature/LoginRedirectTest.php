<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
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
