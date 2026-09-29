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

        $wireguardEnabled = filled(config('hotfii.wireguard.server_public_key'))
            && filled(config('hotfii.wireguard.endpoint'))
            && filled($management['wireguard_address'] ?? null);

        $radiusHost = $wireguardEnabled
            ? (string) config('hotfii.wireguard.server_address', '10.77.0.1')
            : (string) $base['radius_host'];

        $wireguardBlock = '';

        if ($wireguardEnabled) {
            $wireguardBlock = strtr(<<<'ROS'
# Configure the private HotFii WireGuard management tunnel.
:if ([:len [/interface wireguard find where name="hotfii-wg"]] = 0) do={
    /interface wireguard add name="hotfii-wg" comment=$hotfiiComment
}

/interface wireguard peers remove [find where interface="hotfii-wg" comment=$hotfiiComment]
/interface wireguard peers add interface="hotfii-wg" public-key="{{WG_SERVER_PUBLIC_KEY}}" endpoint-address="{{WG_ENDPOINT}}" endpoint-port={{WG_PORT}} allowed-address="{{WG_ALLOWED}}" persistent-keepalive=25s comment=$hotfiiComment

/ip address remove [find where interface="hotfii-wg" comment=$hotfiiComment]
/ip address add address="{{WG_ADDRESS}}" interface="hotfii-wg" comment=$hotfiiComment

# Route only HotFii management traffic through WireGuard.
/ip route remove [find where dst-address="{{WG_SERVER_ADDRESS}}/32" comment=$hotfiiComment]
/ip route add dst-address="{{WG_SERVER_ADDRESS}}/32" gateway="hotfii-wg" comment=$hotfiiComment

# Enrol this router's automatically generated WireGuard public key with HotFii.
:local wgPublicKey [/interface wireguard get [find where name="hotfii-wg"] public-key]
:local wgEnrollBody ("{\"public_key\":\"" . $wgPublicKey . "\"}")

:local wgEnrollResult [/tool fetch url="{{WG_ENROLL_URL}}" http-method=post http-header-field="Content-Type: application/json,X-HotFii-Secret: {{DEVICE_SECRET}}" http-data=$wgEnrollBody check-certificate=yes-without-crl output=user as-value]

:if (($wgEnrollResult->"status") != "finished") do={
    :error "HotFii WireGuard enrollment failed"
}

# Trigger and verify the tunnel before moving RADIUS onto it.
:local wgReady false
:for i from=1 to=10 do={
    :if ([/ping address={{WG_SERVER_ADDRESS}} count=1 interval=500ms] > 0) do={
        :set wgReady true
    }
    :if ($wgReady = false) do={
        :delay 1s
    }
}

:if ($wgReady = false) do={
    :error "HotFii WireGuard tunnel did not establish"
}

ROS, [
                '{{WG_SERVER_PUBLIC_KEY}}' => $quote(config('hotfii.wireguard.server_public_key')),
                '{{WG_ENDPOINT}}' => $quote(config('hotfii.wireguard.endpoint')),
                '{{WG_PORT}}' => (string) config('hotfii.wireguard.port'),
                '{{WG_ALLOWED}}' => $quote(config('hotfii.wireguard.allowed_addresses')),
                '{{WG_ADDRESS}}' => $quote($management['wireguard_address'] ?? ''),
                '{{WG_SERVER_ADDRESS}}' => $quote(config('hotfii.wireguard.server_address', '10.77.0.1')),
                '{{WG_ENROLL_URL}}' => $quote(route('api.v1.network-devices.wireguard.enroll', ['device' => $device])),
                '{{DEVICE_SECRET}}' => $quote($device->radius_secret),
            ]);
        }

        $template = <<<'ROS'
# HotFii RouterOS 7 provisioning
:do {
:local hotfiiComment "HotFii managed - do not rename"

{{WIREGUARD_BLOCK}}

# Configure HotFii RADIUS.
# When WireGuard is enabled this is the permanent private HotFii address.
 /radius remove [find where comment=$hotfiiComment]
 /radius remove [find where address="{{RADIUS_HOST}}"]
 /radius add address="{{RADIUS_HOST}}" secret="{{RADIUS_SECRET}}" service=hotspot authentication-port={{AUTH_PORT}} accounting-port={{ACCT_PORT}} timeout=3s require-message-auth=yes-for-request-resp comment=$hotfiiComment
 /radius incoming set accept=yes port={{COA_PORT}}

# HotFii always owns the captive-portal profile and login page.
# Preserve the customer's WAN, LAN, bridge, DHCP and HotSpot address pool,
# but never reuse legacy MikroTicket/Mikhmon/other portal branding.
:local hotfiiProfile "hotfii-profile"
:local hotfiiHtmlDir "hotspot"

# Routers with flash storage must keep portal files under flash so they
# survive a reboot. Routers without flash use the normal hotspot directory.
:if ([:len [/file find where name="flash"]] > 0) do={
    :set hotfiiHtmlDir "flash/hotspot"
}

:if ([:len [/ip hotspot profile find where name=$hotfiiProfile]] = 0) do={
    /ip hotspot profile add name=$hotfiiProfile
}

# If no HotSpot server exists, create one on the active DHCP/LAN interface.
# Do not replace DHCP, the customer's IP pool, LAN addresses or WAN settings.
:if ([:len [/ip hotspot find]] = 0) do={
    :local hotspotInterface ""

    :foreach dhcpId in=[/ip dhcp-server find where disabled=no] do={
        :if ([:len $hotspotInterface] = 0) do={
            :local candidateInterface [/ip dhcp-server get $dhcpId interface]
            :if ([:len [/ip address find where interface=$candidateInterface disabled=no]] > 0) do={
                :set hotspotInterface $candidateInterface
            }
        }
    }

    :if ([:len $hotspotInterface] = 0) do={
        :error "HotFii could not detect an active DHCP/LAN interface for HotSpot"
    }

    /ip hotspot add name="hotfii-hotspot" interface=$hotspotInterface profile=$hotfiiProfile address-pool=none disabled=no comment=$hotfiiComment
}

# Derive the gateway address from the first active HotSpot interface.
:local hotfiiHotspotAddress ""
:foreach hotspotId in=[/ip hotspot find where disabled=no] do={
    :if ([:len $hotfiiHotspotAddress] = 0) do={
        :local hotspotInterface [/ip hotspot get $hotspotId interface]
        :foreach addressId in=[/ip address find where interface=$hotspotInterface disabled=no] do={
            :if ([:len $hotfiiHotspotAddress] = 0) do={
                :local hotspotCidr [/ip address get $addressId address]
                :local slashPos [:find $hotspotCidr "/"]
                :if ([:typeof $slashPos] != "nil") do={
                    :set hotfiiHotspotAddress [:pick $hotspotCidr 0 $slashPos]
                }
            }
        }
    }
}

# Configure one HotFii-owned profile. Explicitly clear any legacy DNS name
# and point RouterOS at the HotFii-managed HTML directory.
:if ([:len $hotfiiHotspotAddress] > 0) do={
    /ip hotspot profile set [find where name=$hotfiiProfile] hotspot-address=$hotfiiHotspotAddress use-radius=yes radius-accounting=yes radius-interim-update=1m login-by=http-pap,cookie dns-name="" html-directory=$hotfiiHtmlDir html-directory-override=""
} else={
    /ip hotspot profile set [find where name=$hotfiiProfile] use-radius=yes radius-accounting=yes radius-interim-update=1m login-by=http-pap,cookie dns-name="" html-directory=$hotfiiHtmlDir html-directory-override=""
}

# Every HotSpot server is now served by HotFii. Existing server interfaces
# and address pools are intentionally preserved.
:foreach hotspotId in=[/ip hotspot find] do={
    /ip hotspot set $hotspotId profile=$hotfiiProfile
}

 /ip hotspot walled-garden remove [find where comment=$hotfiiComment]
 /ip hotspot walled-garden add dst-host="{{PORTAL_HOST}}" comment=$hotfiiComment
 /ip hotspot walled-garden add dst-host="*.paystack.com" comment=$hotfiiComment

# Install the device-specific HotFii redirect page into the exact directory
# used by the HotFii profile.
:if ([:len [/file find where name=$hotfiiHtmlDir]] = 0) do={
    /file add name=$hotfiiHtmlDir type=directory
}

:local hotfiiLoginTmp ($hotfiiHtmlDir . "/hotfii-login.tmp")
:local hotfiiLoginPath ($hotfiiHtmlDir . "/login.html")

:if ([:len [/file find where name=$hotfiiLoginTmp]] > 0) do={
    /file remove [find where name=$hotfiiLoginTmp]
}

/tool fetch url="{{PORTAL_LOGIN_URL}}" dst-path=$hotfiiLoginTmp keep-result=yes check-certificate=yes-without-crl

:if ([:len [/file find where name=$hotfiiLoginTmp]] = 0) do={
    :error "HotFii captive portal download failed"
}

:if ([:len [/file find where name=$hotfiiLoginPath]] > 0) do={
    /file remove [find where name=$hotfiiLoginPath]
}

/file set [find where name=$hotfiiLoginTmp] name=$hotfiiLoginPath

# Fail provisioning instead of reporting success if HotFii is not actually
# the profile serving every HotSpot server or the login page is missing.
:foreach hotspotId in=[/ip hotspot find] do={
    :if ([/ip hotspot get $hotspotId profile] != $hotfiiProfile) do={
        :error "HotFii failed to take ownership of an active HotSpot server"
    }
}

:if ([:len [/file find where name=$hotfiiLoginPath]] = 0) do={
    :error "HotFii login page verification failed"
}

# Restricted monitoring account.
:if ([:len [/user group find where name="hotfii-monitoring"]] = 0) do={
    /user group add name="hotfii-monitoring" policy=read,api,test
}
/user remove [find where name="{{API_USER}}"]
/user add name="{{API_USER}}" group="hotfii-monitoring" password="{{API_PASSWORD}}" comment=$hotfiiComment

/system identity set name="hotfii-{{NAS_ID}}"

# Install a signed one-minute heartbeat.
 /system scheduler remove [find where name="hotfii-heartbeat"]
 /system script remove [find where name="hotfii-heartbeat"]

 /system script add name="hotfii-heartbeat" policy=ftp,read,test source={
    :local firmwareVersion [/system resource get version]
    :local heartbeatUrl "{{HEARTBEAT_URL}}"
    :local heartbeatSecret "{{RADIUS_SECRET}}"
    :local heartbeatBody ("{\"_hotfii_secret\":\"" . $heartbeatSecret . "\",\"firmware_version\":\"" . $firmwareVersion . "\"}")

    /tool fetch \
        url=$heartbeatUrl \
        http-method=post \
        http-header-field="Content-Type:application/json" \
        http-data=$heartbeatBody \
        check-certificate=yes-without-crl \
        output=none
}

 /system scheduler add name="hotfii-heartbeat" interval=1m on-event="hotfii-heartbeat" policy=ftp,read,test

# Send the first heartbeat immediately.
 /system script run hotfii-heartbeat

:put "HotFii provisioning completed successfully"
} on-error={
    :put "HotFii provisioning failed. Check the preceding RouterOS error."
}
ROS;

        $script = strtr($template, [
            '{{WIREGUARD_BLOCK}}' => $wireguardBlock,
            '{{RADIUS_HOST}}' => $quote($radiusHost),
            '{{RADIUS_SECRET}}' => $quote($device->radius_secret),
            '{{AUTH_PORT}}' => (string) $base['authentication_port'],
            '{{ACCT_PORT}}' => (string) $base['accounting_port'],
            '{{COA_PORT}}' => (string) $base['coa_port'],
            '{{PORTAL_HOST}}' => $quote(parse_url(config('app.url'), PHP_URL_HOST)),
            '{{PORTAL_LOGIN_URL}}' => $quote(route('portal.mikrotik-login', $device)),
            '{{HEARTBEAT_URL}}' => $quote(route('api.v1.network-devices.heartbeat', ['device' => $device])),
            '{{API_USER}}' => $quote($management['api_username'] ?? 'hotfii-monitor'),
            '{{API_PASSWORD}}' => $quote($management['api_password'] ?? ''),
            '{{NAS_ID}}' => $quote($device->nas_identifier),
        ]);

        return [
            ...$base,
            'radius_host' => $radiusHost,
            'method' => 'script',
            'routeros_minimum' => '7.0',
            'script' => $script,
            'management_username' => $management['api_username'] ?? 'hotfii-monitor',
            'wireguard_enabled' => $wireguardEnabled,
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