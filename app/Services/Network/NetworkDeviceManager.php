<?php

namespace App\Services\Network;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\RouterVendor;
use App\Domain\Enums\SupportLevel;
use App\Events\NetworkDeviceStatusChanged;
use App\Models\Location;
use App\Models\NetworkDevice;
use App\Services\Billing\TrialManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NetworkDeviceManager
{
    public function __construct(
        private readonly RouterAdapterRegistry $adapters,
        private readonly TrialManager $trials,
    ) {}

    public function create(Location $location, array $attributes): NetworkDevice
    {
        return DB::transaction(function () use ($location, $attributes) {
            $vendor = RouterVendor::from($attributes['vendor']);
            $adapter = $this->adapters->for($vendor);
            $device = NetworkDevice::create([
                'organization_id' => $location->organization_id,
                'location_id' => $location->id,
                'name' => $attributes['name'],
                'vendor' => $vendor,
                'model' => $attributes['model'] ?? null,
                'adapter' => $adapter->key(),
                'support_level' => $vendor === RouterVendor::Mikrotik ? SupportLevel::Beta : SupportLevel::Compatible,
                'status' => NetworkDeviceStatus::Pending,
                'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
                'radius_secret' => Str::password(32, true, true, false),
                'management_address' => $attributes['management_address'] ?? null,
                'capabilities' => $adapter->capabilities(),
            ]);

            if ($vendor === RouterVendor::Mikrotik) {
                $thirdOctet = intdiv($device->id, 250) % 250;
                $fourthOctet = ($device->id % 250) + 2;
                $device->update(['management_config' => [
                    'api_username' => 'hotfii-monitor',
                    'api_password' => Str::password(24, true, true, false),
                    'wireguard_address' => "10.77.{$thirdOctet}.{$fourthOctet}/32",
                ]]);
            }

            DB::table('nas')->insert([
                'nasname' => $device->management_address ?: $device->nas_identifier,
                'shortname' => $device->nas_identifier,
                'type' => $vendor->value,
                'secret' => $device->radius_secret,
                'description' => 'HotFii '.$vendor->label(),
                'network_device_id' => $device->id,
            ]);

            return $device->refresh();
        });
    }

    public function runTests(NetworkDevice $device): string
    {
        $run = (string) Str::uuid();
        $device->update(['status' => NetworkDeviceStatus::Testing]);

        foreach ($this->adapters->byKey($device->adapter)->tests($device) as $test) {
            $device->tests()->create([
                'run_uuid' => $run,
                'test_key' => $test['key'],
                'status' => $test['status'],
                'message' => $test['message'] ?? null,
                'details' => $test['details'] ?? null,
                'checked_at' => now(),
            ]);
        }

        $this->refreshStatus($device, $run);
        return $run;
    }

    public function markEvidence(NetworkDevice $device, string $testKey, string $message, array $details = []): void
    {
        $test = $device->tests()->where('test_key', $testKey)->latest('id')->first();
        if (! $test) {
            return;
        }

        $test->update([
            'status' => 'passed',
            'message' => $message,
            'details' => $details,
            'checked_at' => now(),
        ]);
        $this->refreshStatus($device, $test->run_uuid);
    }

    public function refreshStatus(NetworkDevice $device, string $run): void
    {
        $tests = $device->tests()->where('run_uuid', $run)->get();
        $status = match (true) {
            $tests->contains('status', 'failed') => NetworkDeviceStatus::Failed,
            $tests->isNotEmpty() && $tests->every(fn ($test) => $test->status === 'passed') => NetworkDeviceStatus::Online,
            default => NetworkDeviceStatus::Testing,
        };

        if ($device->status !== $status) {
            $device->update(['status' => $status]);
            NetworkDeviceStatusChanged::dispatch($device->refresh());
        }

        $organization = $device->organization;
        if ($status === NetworkDeviceStatus::Online
            && $organization->mode === OrganizationMode::Internal
            && $organization->status === OrganizationStatus::Sandbox) {
            $this->trials->start($organization);
        }
    }
}