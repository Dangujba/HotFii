<?php

namespace Tests\Feature\Api;

use App\Contracts\Network\RouterAdapter;
use App\Jobs\RunNetworkDeviceTests;
use App\Models\AccessPlan;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\User;
use App\Services\Network\RouterAdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MobileNetworkAndSessionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private NetworkDevice $router;

    private AccessPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Network Operator',
            'slug' => 'network-operator',
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'micro_seller',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->owner = User::factory()->create();
        $this->organization->users()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
        $location = $this->organization->locations()->create(['name' => 'Main site', 'timezone' => 'Africa/Lagos']);
        $this->router = $this->organization->networkDevices()->create([
            'location_id' => $location->id,
            'name' => 'Main router',
            'vendor' => 'generic',
            'model' => 'CCR Test',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'online',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => 'must-never-leak',
            'management_address' => '10.77.0.2',
            'management_config' => ['api_password' => 'also-secret'],
            'capabilities' => ['radius_auth', 'coa_disconnect'],
            'health' => ['uptime' => 3600, 'nested' => ['secret' => 'hidden']],
            'last_heartbeat_at' => now(),
        ]);
        $this->plan = $this->organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'is_active' => true,
        ]);
    }

    public function test_router_catalog_and_detail_are_filterable_tenant_scoped_and_secret_free(): void
    {
        $run = (string) Str::uuid();
        foreach (['configuration', 'heartbeat', 'coa'] as $key) {
            $this->router->tests()->create([
                'run_uuid' => $run,
                'test_key' => $key,
                'status' => $key === 'heartbeat' ? 'pending' : 'passed',
                'message' => $key.' result',
                'checked_at' => now(),
            ]);
        }
        $token = $this->token($this->owner);

        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.routers.index', [
            'organization' => $this->organization,
            'search' => 'CCR',
            'status' => 'online',
        ]))->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.online', 1)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.routers.0.name', 'Main router');

        $detail = $this->withToken($token)->getJson(route('api.v1.mobile.organizations.routers.show', [
            'organization' => $this->organization,
            'device' => $this->router,
        ]));
        $detail->assertOk()
            ->assertJsonPath('data.router.setup.configured', true)
            ->assertJsonPath('data.router.health.uptime', 3600)
            ->assertJsonCount(2, 'data.tests')
            ->assertJsonMissing(['key' => 'coa']);
        $this->assertStringNotContainsString('must-never-leak', $detail->getContent());
        $this->assertStringNotContainsString('also-secret', $detail->getContent());

        $other = $this->organization('Other network');
        $otherRouter = $this->router($other, 'Hidden router');
        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.routers.show', [
            'organization' => $this->organization,
            'device' => $otherRouter,
        ]))->assertNotFound();
    }

    public function test_authorized_operator_can_queue_readiness_tests(): void
    {
        Queue::fake();

        $this->withToken($this->token($this->owner))
            ->postJson(route('api.v1.mobile.organizations.routers.test', [
                'organization' => $this->organization,
                'device' => $this->router,
            ]))
            ->assertAccepted();

        Queue::assertPushed(RunNetworkDeviceTests::class, fn ($job) => $job->device->is($this->router));
    }

    public function test_live_and_recent_sessions_are_separate_filterable_and_paginated(): void
    {
        $active = $this->hotspotSession('active', 'live-user');
        $this->hotspotSession('stopped', 'recent-user');
        $token = $this->token($this->owner);

        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.sessions.index', [
            'organization' => $this->organization,
            'view' => 'live',
            'router' => $this->router->uuid,
        ]))->assertOk()
            ->assertJsonPath('data.summary.live', 1)
            ->assertJsonPath('data.summary.recent', 1)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.sessions.0.id', $active->uuid)
            ->assertJsonPath('data.sessions.0.can_disconnect', true);

        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.sessions.index', [
            'organization' => $this->organization,
            'view' => 'recent',
            'search' => 'recent-user',
        ]))->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.sessions.0.status', 'stopped');

        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.sessions.index', [
            'organization' => $this->organization,
            'view' => 'recent',
            'status' => 'active',
        ]))->assertOk()->assertJsonPath('data.pagination.total', 0);
    }

    public function test_disconnect_is_only_reported_after_the_adapter_confirms_it(): void
    {
        $session = $this->hotspotSession('active', 'disconnect-me');
        $this->confirmingAdapter(true);

        $this->withToken($this->token($this->owner))
            ->postJson(route('api.v1.mobile.organizations.sessions.disconnect', [
                'organization' => $this->organization,
                'session' => $session,
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'stopped')
            ->assertJsonPath('data.terminate_cause', 'Admin-Reset');

        $this->assertSame('stopped', $session->fresh()->status);
    }

    public function test_failed_disconnect_returns_validation_error_and_restores_active_status(): void
    {
        $session = $this->hotspotSession('active', 'stay-online');
        $this->confirmingAdapter(false);

        $this->withToken($this->token($this->owner))
            ->postJson(route('api.v1.mobile.organizations.sessions.disconnect', [
                'organization' => $this->organization,
                'session' => $session,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');

        $this->assertSame('active', $session->fresh()->status);
    }

    public function test_viewer_can_read_network_activity_but_cannot_change_it(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer', 'joined_at' => now()]);
        $session = $this->hotspotSession('active', 'viewer-session');
        $token = $this->token($viewer);

        $this->withToken($token)
            ->getJson(route('api.v1.mobile.organizations.routers.index', $this->organization))
            ->assertOk()->assertJsonPath('data.permissions.can_manage', false);
        $this->withToken($token)
            ->postJson(route('api.v1.mobile.organizations.sessions.disconnect', [
                'organization' => $this->organization,
                'session' => $session,
            ]))->assertForbidden();
    }

    private function hotspotSession(string $status, string $username): HotspotSession
    {
        return $this->organization->sessions()->create([
            'network_device_id' => $this->router->id,
            'access_plan_id' => $this->plan->id,
            'radius_username' => $username,
            'acct_session_id' => (string) Str::uuid(),
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'client_name' => 'Pixel',
            'ip_address' => '10.10.0.20',
            'status' => $status,
            'input_bytes' => 1024,
            'output_bytes' => 2048,
            'started_at' => now()->subMinutes(5),
            'stopped_at' => $status === 'stopped' ? now() : null,
        ]);
    }

    private function confirmingAdapter(bool $confirmed): void
    {
        $adapter = Mockery::mock(RouterAdapter::class);
        $adapter->shouldReceive('disconnect')->once()->andReturn($confirmed);
        $registry = Mockery::mock(RouterAdapterRegistry::class);
        $registry->shouldReceive('byKey')->with('generic-radius')->andReturn($adapter);
        $this->app->instance(RouterAdapterRegistry::class, $registry);
    }

    private function organization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'micro_seller',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }

    private function router(Organization $organization, string $name): NetworkDevice
    {
        $location = $organization->locations()->create(['name' => 'Site']);

        return $organization->networkDevices()->create([
            'location_id' => $location->id,
            'name' => $name,
            'vendor' => 'generic',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'online',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(8), ['mobile'])->plainTextToken;
    }
}
