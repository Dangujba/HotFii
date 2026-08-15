<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\RouterVendor;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_open_another_organizations_router(): void
    {
        $user = User::factory()->create();
        $own = $this->organization('own-network');
        $other = $this->organization('other-network');
        $own->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        $location = $other->locations()->create(['name' => 'Other site']);
        $device = NetworkDevice::create([
            'organization_id' => $other->id,
            'location_id' => $location->id,
            'name' => 'Hidden router',
            'vendor' => RouterVendor::Generic,
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'pending',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $own->id])
            ->get(route('network.devices.show', $device))
            ->assertNotFound();
    }

    private function organization(string $slug): Organization
    {
        return Organization::create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Sandbox,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
    }
}