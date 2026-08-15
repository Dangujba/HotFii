<?php

namespace App\Services\Network;

use App\Models\NetworkDevice;
use Throwable;

class MikrotikRouterOsAdapter extends GenericRadiusAdapter
{
    public function key(): string { return 'mikrotik-routeros'; }

    public function capabilities(): array
    {
        return array_values(array_unique([
            ...parent::capabilities(),
            'automatic_provisioning', 'health_monitoring', 'configuration_sync', 'remote_access', 'wireguard',
        ]));
    }

    public function provisioning(NetworkDevice $device): array
    {
        $base = parent::provisioning($device);
        $management = $device->management_config ?? [];
        $quote = fn (?string $value) => str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);

        $template = <<<'ROS'
# HotFii RouterOS 7 provisioning
:local hotfiiComment "HotFii managed - do not rename"
/radius remove [find where comment=$hotfiiComment]
/radius add address={{RADIUS_HOST}} secret="{{RADIUS_SECRET}}" service=hotspot authentication-port={{AUTH_PORT}} accounting-port={{ACCT_PORT}} timeout=3s comment=$hotfiiComment
/radius incoming set accept=yes port={{COA_PORT}}
/ip hotspot profile set [find where name="default"] use-radius=yes radius-accounting=yes radius-interim-update=1m login-by=http-pap,cookie
/ip hotspot walled-garden remove [find where comment=$hotfiiComment]
/ip hotspot walled-garden add dst-host="{{PORTAL_HOST}}" comment=$hotfiiComment
/ip hotspot walled-garden add dst-host="*.paystack.com" comment=$hotfiiComment
:if ([:len [/user group find where name="hotfii-monitoring"]] = 0) do={/user group add name="hotfii-monitoring" policy=read,api,test}
/user remove [find where name="{{API_USER}}"]
/user add name="{{API_USER}}" group="hotfii-monitoring" password="{{API_PASSWORD}}" comment=$hotfiiComment
/system identity set name="hotfii-{{NAS_ID}}"
:put "HotFii RADIUS and captive portal configuration applied"
ROS;

        $script = strtr($template, [
            '{{RADIUS_HOST}}' => $quote($base['radius_host']),
            '{{RADIUS_SECRET}}' => $quote($device->radius_secret),
            '{{AUTH_PORT}}' => (string) $base['authentication_port'],
            '{{ACCT_PORT}}' => (string) $base['accounting_port'],
            '{{COA_PORT}}' => (string) $base['coa_port'],
            '{{PORTAL_HOST}}' => $quote(parse_url(config('app.url'), PHP_URL_HOST)),
            '{{API_USER}}' => $quote($management['api_username'] ?? 'hotfii-monitor'),
            '{{API_PASSWORD}}' => $quote($management['api_password'] ?? ''),
            '{{NAS_ID}}' => $quote($device->nas_identifier),
        ]);

        if (config('hotfii.wireguard.server_public_key') && config('hotfii.wireguard.endpoint')) {
            $wireguard = <<<'ROS'

:if ([:len [/interface wireguard find where name="hotfii-wg"]] = 0) do={/interface wireguard add name="hotfii-wg" comment=$hotfiiComment}
/interface wireguard peers remove [find where comment=$hotfiiComment]
/interface wireguard peers add interface="hotfii-wg" public-key="{{WG_PUBLIC_KEY}}" endpoint-address="{{WG_ENDPOINT}}" endpoint-port={{WG_PORT}} allowed-address="{{WG_ALLOWED}}" persistent-keepalive=25s comment=$hotfiiComment
/ip address remove [find where comment=$hotfiiComment]
/ip address add address="{{WG_ADDRESS}}" interface="hotfii-wg" comment=$hotfiiComment
:put ("HotFii WireGuard public key: " . [/interface wireguard get [find where name="hotfii-wg"] public-key])
ROS;
            $script .= strtr($wireguard, [
                '{{WG_PUBLIC_KEY}}' => $quote(config('hotfii.wireguard.server_public_key')),
                '{{WG_ENDPOINT}}' => $quote(config('hotfii.wireguard.endpoint')),
                '{{WG_PORT}}' => (string) config('hotfii.wireguard.port'),
                '{{WG_ALLOWED}}' => $quote(config('hotfii.wireguard.allowed_addresses')),
                '{{WG_ADDRESS}}' => $quote($management['wireguard_address'] ?? ''),
            ]);
        }

        return [
            ...$base,
            'method' => 'script',
            'routeros_minimum' => '7.0',
            'script' => $script,
            'management_username' => $management['api_username'] ?? 'hotfii-monitor',
            'wireguard_address' => $management['wireguard_address'] ?? null,
        ];
    }

    public function provision(NetworkDevice $device): array
    {
        return ['status' => 'script_ready', 'configuration' => $this->provisioning($device)];
    }

    public function synchronize(NetworkDevice $device): array
    {
        return [
            'status' => 'script_ready',
            'message' => 'Re-running the idempotent hotfii-managed script synchronizes RouterOS configuration.',
        ];
    }

    public function normalizeError(Throwable $exception): array
    {
        $message = $exception->getMessage();
        return [
            'code' => str_contains(strtolower($message), 'timed out') ? 'router_timeout' : 'routeros_error',
            'message' => $message,
            'retryable' => str_contains(strtolower($message), 'timed out'),
        ];
    }
}