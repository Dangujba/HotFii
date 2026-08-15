<?php

namespace App\Services\Network;

use App\Contracts\Network\RouterAdapter;
use App\Models\AccessPlan;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Process;
use Throwable;

class GenericRadiusAdapter implements RouterAdapter
{
    public function key(): string { return 'generic-radius'; }

    public function discover(array $context = []): array
    {
        return ['method' => 'manual', 'candidates' => [], 'context' => $context];
    }

    public function capabilities(): array
    {
        return [
            'radius_auth', 'radius_accounting', 'captive_portal', 'coa_disconnect',
            'time_limits', 'data_limits', 'speed_limits', 'simultaneous_use',
        ];
    }

    public function provisioning(NetworkDevice $device): array
    {
        return [
            'method' => 'manual',
            'radius_host' => config('hotfii.radius.host'),
            'authentication_port' => config('hotfii.radius.auth_port'),
            'accounting_port' => config('hotfii.radius.accounting_port'),
            'coa_port' => config('hotfii.radius.coa_port'),
            'nas_identifier' => $device->nas_identifier,
            'radius_secret' => $device->radius_secret,
            'portal_url' => route('portal.show', ['device' => $device]),
            'heartbeat_url' => route('api.v1.network-devices.heartbeat', ['device' => $device]),
            'walled_garden' => [
                parse_url(config('app.url'), PHP_URL_HOST),
                'checkout.paystack.com',
                'api.paystack.co',
            ],
        ];
    }

    public function provision(NetworkDevice $device): array
    {
        return ['status' => 'manual_configuration_required', 'configuration' => $this->provisioning($device)];
    }

    public function testManagementConnection(NetworkDevice $device): array
    {
        $recent = $device->last_heartbeat_at?->gt(now()->subSeconds(90)) ?? false;
        return [
            'key' => 'heartbeat',
            'status' => $recent ? 'passed' : 'pending',
            'message' => $recent ? 'A recent signed heartbeat was received.' : 'Waiting for a recent device heartbeat.',
        ];
    }

    public function testRadiusAuthentication(NetworkDevice $device): array
    {
        return ['key' => 'radius_auth', 'status' => 'pending', 'message' => 'Send a test Access-Request from the configured NAS.'];
    }

    public function testAccounting(NetworkDevice $device): array
    {
        return ['key' => 'accounting', 'status' => 'pending', 'message' => 'Waiting for Accounting-Start and interim records.'];
    }

    public function testCaptivePortal(NetworkDevice $device): array
    {
        return ['key' => 'captive_portal', 'status' => 'pending', 'message' => 'Open the SSID from a client and confirm redirect to HotFii.'];
    }

    public function tests(NetworkDevice $device): array
    {
        return [
            ['key' => 'configuration', 'status' => 'passed', 'message' => 'Vendor-aware RADIUS configuration generated.'],
            $this->testManagementConnection($device),
            $this->testRadiusAuthentication($device),
            $this->testAccounting($device),
            $this->testCaptivePortal($device),
            ['key' => 'coa', 'status' => 'pending', 'message' => 'A live test session is required for disconnect verification.'],
        ];
    }

    public function disconnect(HotspotSession $session): bool
    {
        $device = $session->networkDevice;
        if (! $session->acct_session_id || ! $device->management_address) {
            return false;
        }

        $host = parse_url(
            str_contains($device->management_address, '://') ? $device->management_address : 'udp://'.$device->management_address,
            PHP_URL_HOST,
        );
        if (! $host) {
            return false;
        }

        $clean = fn (?string $value) => str_replace(["\r", "\n", '"'], ['', '', '\"'], (string) $value);
        $payload = 'User-Name = "'.$clean($session->radius_username).'"'.PHP_EOL
            .'Acct-Session-Id = "'.$clean($session->acct_session_id).'"'.PHP_EOL
            .'Calling-Station-Id = "'.$clean($session->mac_address).'"'.PHP_EOL;

        $result = Process::timeout(10)
            ->input($payload)
            ->run(['radclient', '-x', $host.':'.config('hotfii.radius.coa_port'), 'disconnect', $device->radius_secret]);

        return $result->successful() && str_contains($result->output(), 'Disconnect-ACK');
    }

    public function planAttributes(AccessPlan $plan): array
    {
        return array_filter([
            'Session-Timeout' => $plan->duration_minutes ? $plan->duration_minutes * 60 : null,
            'Simultaneous-Use' => $plan->simultaneous_use,
            'Mikrotik-Rate-Limit' => ($plan->download_kbps || $plan->upload_kbps)
                ? ($plan->upload_kbps ?: $plan->download_kbps).'k/'.($plan->download_kbps ?: $plan->upload_kbps).'k'
                : null,
            'HotFii-Data-Limit' => $plan->data_limit_bytes,
        ], fn ($value) => $value !== null);
    }

    public function healthMetrics(NetworkDevice $device): array
    {
        return [
            'status' => $device->status->value,
            'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            'metrics' => $device->health ?? [],
        ];
    }

    public function synchronize(NetworkDevice $device): array
    {
        return ['status' => 'manual', 'message' => 'Apply the generated configuration on the device/controller.'];
    }

    public function normalizeError(Throwable $exception): array
    {
        return [
            'code' => class_basename($exception),
            'message' => $exception->getMessage(),
            'retryable' => false,
        ];
    }
}