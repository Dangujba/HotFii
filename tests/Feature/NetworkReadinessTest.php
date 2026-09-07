<?php

namespace Tests\Feature;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\RouterVendor;
use App\Jobs\MarkOfflineNetworkDevices;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\User;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Network\RouterAdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NetworkReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_disconnect_checks_are_not_part_of_adapter_readiness(): void
    {
        [, $device] = $this->device();
        $registry = app(RouterAdapterRegistry::class);

        foreach ([
            'generic-radius',
            'mikrotik-routeros',
            'unifi-network',
            'tp-link-omada',
            'openwrt-coovachilli',
        ] as $adapter) {
            $keys = array_column($registry->byKey($adapter)->tests($device), 'key');

            $this->assertNotContains('coa', $keys, $adapter);
            $this->assertNotContains('disconnect', $keys, $adapter);
        }
    }

    public function test_legacy_disconnect_checks_neither_block_online_status_nor_appear_in_test_center(): void
    {
        [$organization, $device] = $this->device();
        $run = (string) Str::uuid();

        foreach (['configuration', 'heartbeat', 'radius_auth', 'accounting', 'captive_portal'] as $key) {
            $device->tests()->create([
                'run_uuid' => $run,
                'test_key' => $key,
                'status' => 'passed',
                'message' => ucfirst($key).' passed.',
                'checked_at' => now(),
            ]);
        }

        foreach (['coa', 'disconnect'] as $key) {
            $device->tests()->create([
                'run_uuid' => $run,
                'test_key' => $key,
                'status' => 'pending',
                'message' => 'Legacy '.$key.' readiness row.',
                'checked_at' => now(),
            ]);
        }

        app(NetworkDeviceManager::class)->refreshStatus($device, $run);

        $this->assertSame(NetworkDeviceStatus::Online, $device->fresh()->status);

        $user = User::factory()->create();
        $organization->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->get(route('network.devices.show', $device))
            ->assertOk()
            ->assertDontSee('Legacy coa readiness row.')
            ->assertDontSee('Legacy disconnect readiness row.');
    }

    public function test_stale_testing_device_becomes_offline_but_unconnected_setup_remains_testing(): void
    {
        [, $stale] = $this->device();
        [, $unconnected] = $this->device();

        $stale->update(['last_heartbeat_at' => now()->subMinutes(5)]);

        app(MarkOfflineNetworkDevices::class)->handle();

        $this->assertSame(NetworkDeviceStatus::Offline, $stale->fresh()->status);
        $this->assertSame(NetworkDeviceStatus::Testing, $unconnected->fresh()->status);
    }

    /**
     * @return array{0: Organization, 1: NetworkDevice}
     */
    private function device(): array
    {
        $organization = Organization::create([
            'name' => 'Readiness Test',
            'slug' => 'readiness-test-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Sandbox,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
        $location = $organization->locations()->create(['name' => 'Main site']);
        $device = NetworkDevice::create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'name' => 'Test router',
            'vendor' => RouterVendor::Generic,
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => NetworkDeviceStatus::Testing,
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);

        return [$organization, $device];
    }
}
