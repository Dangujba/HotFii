<?php

namespace App\Services\Network;

use App\Models\AccessPlan;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use Throwable;

class UnifiAdapter extends GenericRadiusAdapter
{
    public function __construct(
        private readonly UnifiNetworkApi $api,
    ) {}

    public function key(): string
    {
        return 'unifi-network';
    }

    public function capabilities(): array
    {
        return [
            'captive_portal',
            'external_portal',
            'api_authorization',
            'api_disconnect',
            'session_tracking',
            'time_limits',
            'data_limits',
            'speed_limits',
            'simultaneous_use',
            'health_monitoring',
        ];
    }

    public function provisioning(NetworkDevice $device): array
    {
        $base = parent::provisioning($device);
        $config = $device->management_config ?? [];

        return [
            ...$base,
            'method' => 'manual',
            'integration' => 'unifi-network-api',
            'api_configured' => filled($config['api_key'] ?? null),
            'host_id' => $config['host_id'] ?? null,
            'site_id' => $config['site_id'] ?? null,
            'site_name' => $config['site_name'] ?? null,
            'external_portal_url' => route('portal.show', $device),
        ];
    }

    public function disconnect(HotspotSession $session): bool
    {
        if ($session->source !== 'unifi') {
            return false;
        }

        $device = $session->networkDevice;
        $config = $device->management_config ?? [];

        $apiKey = $config['api_key'] ?? null;
        $hostId = $config['host_id'] ?? null;
        $siteId = $config['site_id'] ?? null;
        $clientId = $session->external_session_id;

        if (! $apiKey || ! $hostId || ! $siteId || ! $clientId) {
            return false;
        }

        try {
            $this->api->unauthorizeGuest(
                $apiKey,
                $hostId,
                $siteId,
                $clientId,
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function planAttributes(AccessPlan $plan): array
    {
        return array_filter([
            'Session-Timeout' => $plan->duration_minutes
                ? $plan->duration_minutes * 60
                : null,

            'Simultaneous-Use' => $plan->simultaneous_use,
        ], fn ($value) => $value !== null);
    }

    public function testManagementConnection(NetworkDevice $device): array
    {
        $config = $device->management_config ?? [];

        $apiKey = $config['api_key'] ?? null;
        $hostId = $config['host_id'] ?? null;
        $siteId = $config['site_id'] ?? null;

        if (! $apiKey || ! $hostId || ! $siteId) {
            return [
                'key' => 'api_connection',
                'status' => 'pending',
                'message' => 'Connect a UniFi API key and select a site.',
            ];
        }

        try {
            $sites = $this->api->localSites($apiKey, $hostId);

            $found = collect($sites)->contains(
                fn ($site) =>
                    ($site['id'] ?? $site['siteId'] ?? null) === $siteId
            );

            return [
                'key' => 'api_connection',
                'status' => $found ? 'passed' : 'failed',
                'message' => $found
                    ? 'HotFii successfully connected to the selected UniFi site.'
                    : 'The selected UniFi site was not returned.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'key' => 'api_connection',
                'status' => 'failed',
                'message' => 'UniFi API connection failed.',
            ];
        }
    }

    public function testRadiusAuthentication(NetworkDevice $device): array
    {
        $session = $device->sessions()
            ->where('source', 'unifi')
            ->latest('id')
            ->first();

        return [
            'key' => 'api_authorization',
            'status' => $session ? 'passed' : 'pending',
            'message' => $session
                ? 'A UniFi guest was authorized through HotFii.'
                : 'Waiting for the first UniFi guest authorization.',
        ];
    }

    public function testAccounting(NetworkDevice $device): array
    {
        $session = $device->sessions()
            ->where('source', 'unifi')
            ->whereNotNull('session_meta')
            ->latest('id')
            ->first();

        $synced = filled(
            data_get($session?->session_meta, 'last_synced_at')
        );

        return [
            'key' => 'session_tracking',
            'status' => $synced ? 'passed' : 'pending',
            'message' => $synced
                ? 'UniFi session status and usage have been synchronized.'
                : 'Waiting for UniFi session synchronization.',
        ];
    }

    public function testCaptivePortal(NetworkDevice $device): array
    {
        $session = $device->sessions()
            ->where('source', 'unifi')
            ->latest('id')
            ->first();

        return [
            'key' => 'captive_portal',
            'status' => $session ? 'passed' : 'pending',
            'message' => $session
                ? 'The UniFi external portal reached HotFii successfully.'
                : 'Waiting for a guest redirect from UniFi.',
        ];
    }

    public function tests(NetworkDevice $device): array
    {
        $config = $device->management_config ?? [];

        $configured =
            filled($config['api_key'] ?? null)
            && filled($config['host_id'] ?? null)
            && filled($config['site_id'] ?? null);

        $disconnected = $device->sessions()
            ->where('source', 'unifi')
            ->where('status', 'stopped')
            ->where('terminate_cause', 'Admin-Reset')
            ->exists();

        return [
            [
                'key' => 'configuration',
                'status' => $configured ? 'passed' : 'pending',
                'message' => $configured
                    ? 'UniFi API and site configuration is stored securely.'
                    : 'Complete the UniFi Cloud Connection setup.',
            ],

            $this->testManagementConnection($device),
            $this->testCaptivePortal($device),
            $this->testRadiusAuthentication($device),
            $this->testAccounting($device),

            [
                'key' => 'disconnect',
                'status' => $disconnected ? 'passed' : 'pending',
                'message' => $disconnected
                    ? 'A UniFi guest disconnect was completed successfully.'
                    : 'Waiting for a live dashboard disconnect test.',
            ],
        ];
    }

    public function synchronize(NetworkDevice $device): array
    {
        return [
            'status' => 'api_ready',
            'message' => 'UniFi is managed through the official Network API.',
        ];
    }
}
