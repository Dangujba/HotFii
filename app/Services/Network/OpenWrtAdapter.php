<?php

namespace App\Services\Network;

use App\Models\AccessPlan;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

class OpenWrtAdapter extends GenericRadiusAdapter
{
    public function key(): string
    {
        return 'openwrt-coovachilli';
    }

    public function capabilities(): array
    {
        return [
            'radius_auth',
            'radius_accounting',
            'captive_portal',
            'external_portal',
            'coa_disconnect',
            'session_tracking',
            'time_limits',
            'data_limits',
            'speed_limits',
            'simultaneous_use',
            'automatic_provisioning',
            'health_monitoring',
            'remote_access',
            'wireguard',
        ];
    }

    public function provisioning(NetworkDevice $device): array
    {
        $base = parent::provisioning($device);
        $management = $device->management_config ?? [];

        $wgAddress = $management['wireguard_address'] ?? null;
        $uamSecret = $management['uam_secret'] ?? null;

        $wireguardEnabled =
            filled(config('hotfii.wireguard.server_public_key'))
            && filled(config('hotfii.wireguard.endpoint'))
            && filled($wgAddress);

        $radiusHost = $wireguardEnabled
            ? (string) config(
                'hotfii.wireguard.server_address',
                '10.77.0.1'
            )
            : (string) $base['radius_host'];

        $guestNetwork =
            $management['guest_network']
            ?? 'hotfii_guest';

        $uamListen =
            $management['uam_listen']
            ?? '192.168.182.1';

        $uamPort =
            (int) ($management['uam_port'] ?? 3990);

        $quote = static function (?string $value): string {
            return "'".str_replace(
                "'",
                "'\"'\"'",
                (string) $value
            )."'";
        };

        $portalHost =
            parse_url(config('app.url'), PHP_URL_HOST)
            ?: 'hotfii.com';

        $template = <<<'SH'
#!/bin/sh
set -eu

echo "=========================================="
echo " HotFii OpenWrt / CoovaChilli Provisioning"
echo "=========================================="

RADIUS_HOST={{RADIUS_HOST}}
RADIUS_SECRET={{RADIUS_SECRET}}
NAS_ID={{NAS_ID}}
AUTH_PORT={{AUTH_PORT}}
ACCT_PORT={{ACCT_PORT}}
COA_PORT={{COA_PORT}}

PORTAL_URL={{PORTAL_URL}}
HOTFII_HOST={{HOTFII_HOST}}
HEARTBEAT_URL={{HEARTBEAT_URL}}

GUEST_NETWORK={{GUEST_NETWORK}}
UAM_LISTEN={{UAM_LISTEN}}
UAM_PORT={{UAM_PORT}}
UAM_SECRET={{UAM_SECRET}}

WG_ENABLED={{WG_ENABLED}}
WG_ADDRESS={{WG_ADDRESS}}
WG_SERVER_PUBLIC_KEY={{WG_SERVER_PUBLIC_KEY}}
WG_ENDPOINT={{WG_ENDPOINT}}
WG_PORT={{WG_PORT}}
WG_ALLOWED={{WG_ALLOWED}}
WG_ENROLL_URL={{WG_ENROLL_URL}}

echo "[1/8] Detecting OpenWrt package manager..."

if command -v apk >/dev/null 2>&1; then
    echo "OpenWrt apk detected."
    apk -U add coova-chilli wireguard-tools curl ca-bundle
elif command -v opkg >/dev/null 2>&1; then
    echo "OpenWrt opkg detected."
    opkg update
    opkg install coova-chilli wireguard-tools curl ca-bundle
else
    echo "ERROR: This system does not appear to be a supported OpenWrt installation."
    exit 1
fi

mkdir -p /etc/hotfii
chmod 700 /etc/hotfii

echo "[2/8] Creating dedicated HotFii guest network..."

# Do NOT take over br-lan.
# HotFii uses its own isolated bridge. An operator may attach a Wi-Fi
# SSID or dedicated Ethernet port to this network after provisioning.

uci -q delete network.br_hotfii || true
uci set network.br_hotfii='device'
uci set network.br_hotfii.name='br-hotfii'
uci set network.br_hotfii.type='bridge'

uci -q delete network.hotfii_guest || true
uci set network.hotfii_guest='interface'
uci set network.hotfii_guest.proto='none'
uci set network.hotfii_guest.device='br-hotfii'

uci commit network

if [ "$WG_ENABLED" = "1" ]; then
    echo "[3/8] Configuring HotFii WireGuard management tunnel..."

    umask 077

    if [ ! -s /etc/hotfii/wg-private.key ]; then
        wg genkey > /etc/hotfii/wg-private.key
    fi

    WG_PRIVATE_KEY="$(cat /etc/hotfii/wg-private.key)"
    WG_PUBLIC_KEY="$(printf '%s' "$WG_PRIVATE_KEY" | wg pubkey)"

    printf '%s\n' "$WG_PUBLIC_KEY" \
        > /etc/hotfii/wg-public.key

    uci -q delete network.hotfii_wg || true
    uci set network.hotfii_wg='interface'
    uci set network.hotfii_wg.proto='wireguard'
    uci set network.hotfii_wg.private_key="$WG_PRIVATE_KEY"
    uci add_list network.hotfii_wg.addresses="$WG_ADDRESS"

    uci -q delete network.hotfii_peer || true
    uci set network.hotfii_peer='wireguard_hotfii_wg'
    uci set network.hotfii_peer.description='HotFii VPS'
    uci set network.hotfii_peer.public_key="$WG_SERVER_PUBLIC_KEY"
    uci set network.hotfii_peer.endpoint_host="$WG_ENDPOINT"
    uci set network.hotfii_peer.endpoint_port="$WG_PORT"
    uci set network.hotfii_peer.persistent_keepalive='25'
    uci add_list network.hotfii_peer.allowed_ips="$WG_ALLOWED"
    uci set network.hotfii_peer.route_allowed_ips='1'

    uci commit network

    echo "[4/8] Restricting HotFii management tunnel..."

    uci -q delete firewall.hotfii_mgmt || true
    uci set firewall.hotfii_mgmt='zone'
    uci set firewall.hotfii_mgmt.name='hotfii_mgmt'
    uci add_list firewall.hotfii_mgmt.network='hotfii_wg'
    uci set firewall.hotfii_mgmt.input='REJECT'
    uci set firewall.hotfii_mgmt.output='ACCEPT'
    uci set firewall.hotfii_mgmt.forward='REJECT'

    uci -q delete firewall.hotfii_coa || true
    uci set firewall.hotfii_coa='rule'
    uci set firewall.hotfii_coa.name='Allow-HotFii-CoA'
    uci set firewall.hotfii_coa.src='hotfii_mgmt'
    uci set firewall.hotfii_coa.src_ip='10.77.0.1'
    uci set firewall.hotfii_coa.proto='udp'
    uci set firewall.hotfii_coa.dest_port="$COA_PORT"
    uci set firewall.hotfii_coa.target='ACCEPT'

    uci commit firewall

    /etc/init.d/network reload
    /etc/init.d/firewall restart

    sleep 5

    echo "Enrolling WireGuard public key with HotFii..."

    curl -fsS \
        -X POST \
        -H "Content-Type: application/json" \
        -H "X-HotFii-Secret: $RADIUS_SECRET" \
        --data "{\"public_key\":\"$WG_PUBLIC_KEY\"}" \
        "$WG_ENROLL_URL" >/tmp/hotfii-wg-enroll.json

    echo "WireGuard enrollment accepted."
else
    echo "[3/8] WireGuard is not configured for this HotFii deployment."
    echo "[4/8] Continuing with public RADIUS."
fi

echo "[5/8] Configuring CoovaChilli..."

if [ -f /etc/config/chilli ] \
   && [ ! -f /etc/config/chilli.before-hotfii ]; then
    cp /etc/config/chilli /etc/config/chilli.before-hotfii
fi

uci -q delete chilli.hotfii || true

uci set chilli.hotfii='chilli'
uci set chilli.hotfii.disabled='0'
uci set chilli.hotfii.tundev='tun0'

# The OpenWrt CoovaChilli init script resolves this UCI network
# to its underlying bridge/device.
uci set chilli.hotfii.network="$GUEST_NETWORK"

uci set chilli.hotfii.net='192.168.182.0/24'
uci set chilli.hotfii.uamlisten="$UAM_LISTEN"
uci set chilli.hotfii.uamport="$UAM_PORT"

uci set chilli.hotfii.radiusserver1="$RADIUS_HOST"
uci set chilli.hotfii.radiusserver2="$RADIUS_HOST"
uci set chilli.hotfii.radiusauthport="$AUTH_PORT"
uci set chilli.hotfii.radiusacctport="$ACCT_PORT"
uci set chilli.hotfii.radiussecret="$RADIUS_SECRET"
uci set chilli.hotfii.radiusnasid="$NAS_ID"

if [ "$WG_ENABLED" = "1" ]; then
    WG_IP="${WG_ADDRESS%/*}"
    uci set chilli.hotfii.radiuslisten="$WG_IP"
    uci set chilli.hotfii.nasip="$WG_IP"
fi

uci set chilli.hotfii.definteriminterval='60'
uci set chilli.hotfii.radiusoriginalurl='1'

uci set chilli.hotfii.uamserver="$PORTAL_URL"
uci set chilli.hotfii.uamsecret="$UAM_SECRET"
uci set chilli.hotfii.uamanydns='1'

# After successful authentication go directly to the userurl
# supplied by HotFii rather than returning to the UAM server.
uci set chilli.hotfii.nouamsuccess='1'

# HotFii and payment domains must be reachable before authentication.
uci set chilli.hotfii.uamallowed="$HOTFII_HOST,checkout.paystack.com,api.paystack.co"

# Listen for standards-based RADIUS Disconnect Requests.
uci set chilli.hotfii.coaport="$COA_PORT"

# Never fail-open when RADIUS is unreachable.
uci set chilli.hotfii.noradallow='0'

uci commit chilli

/etc/init.d/chilli enable
/etc/init.d/chilli restart

echo "[6/8] Installing signed HotFii heartbeat..."

cat > /usr/bin/hotfii-heartbeat <<EOF
#!/bin/sh
FIRMWARE="\$(. /etc/openwrt_release 2>/dev/null; printf '%s' "\${DISTRIB_RELEASE:-unknown}")"

curl -fsS \
    -X POST \
    -H 'Content-Type: application/json' \
    -H 'X-HotFii-Secret: $RADIUS_SECRET' \
    --data "{\"firmware_version\":\"\$FIRMWARE\"}" \
    '$HEARTBEAT_URL' >/dev/null
EOF

chmod 700 /usr/bin/hotfii-heartbeat

sed -i '\#/usr/bin/hotfii-heartbeat#d' /etc/crontabs/root
echo '* * * * * /usr/bin/hotfii-heartbeat >/dev/null 2>&1' \
    >> /etc/crontabs/root

/etc/init.d/cron enable
/etc/init.d/cron restart

echo "[7/8] Sending first heartbeat..."
/usr/bin/hotfii-heartbeat || true

echo "[8/8] Provisioning complete."
echo
echo "HotFii guest network created:"
echo "  UCI network: $GUEST_NETWORK"
echo "  Bridge:      br-hotfii"
echo
echo "IMPORTANT:"
echo "Attach the intended guest Wi-Fi SSID or dedicated Ethernet port"
echo "to the 'hotfii_guest' network. HotFii deliberately did NOT"
echo "take over br-lan, so your management LAN remains untouched."
echo
echo "Then connect one test client and run HotFii readiness tests."
SH;

        $script = strtr($template, [
            '{{RADIUS_HOST}}' =>
                $quote($radiusHost),

            '{{RADIUS_SECRET}}' =>
                $quote($device->radius_secret),

            '{{NAS_ID}}' =>
                $quote($device->nas_identifier),

            '{{AUTH_PORT}}' =>
                $quote((string) config(
                    'hotfii.radius.auth_port',
                    1812
                )),

            '{{ACCT_PORT}}' =>
                $quote((string) config(
                    'hotfii.radius.accounting_port',
                    1813
                )),

            '{{COA_PORT}}' =>
                $quote((string) config(
                    'hotfii.radius.coa_port',
                    3799
                )),

            '{{PORTAL_URL}}' =>
                $quote(route('portal.show', $device)),

            '{{HOTFII_HOST}}' =>
                $quote($portalHost),

            '{{HEARTBEAT_URL}}' =>
                $quote(route(
                    'api.v1.network-devices.heartbeat',
                    ['device' => $device]
                )),

            '{{GUEST_NETWORK}}' =>
                $quote($guestNetwork),

            '{{UAM_LISTEN}}' =>
                $quote($uamListen),

            '{{UAM_PORT}}' =>
                $quote((string) $uamPort),

            '{{UAM_SECRET}}' =>
                $quote((string) $uamSecret),

            '{{WG_ENABLED}}' =>
                $quote($wireguardEnabled ? '1' : '0'),

            '{{WG_ADDRESS}}' =>
                $quote((string) $wgAddress),

            '{{WG_SERVER_PUBLIC_KEY}}' =>
                $quote((string) config(
                    'hotfii.wireguard.server_public_key'
                )),

            '{{WG_ENDPOINT}}' =>
                $quote((string) config(
                    'hotfii.wireguard.endpoint'
                )),

            '{{WG_PORT}}' =>
                $quote((string) config(
                    'hotfii.wireguard.port',
                    51820
                )),

            '{{WG_ALLOWED}}' =>
                $quote((string) config(
                    'hotfii.wireguard.allowed_addresses',
                    '10.77.0.0/16'
                )),

            '{{WG_ENROLL_URL}}' =>
                $quote(route(
                    'api.v1.network-devices.wireguard.enroll',
                    ['device' => $device]
                )),
        ]);

        return [
            ...$base,

            'method' => 'script',
            'integration' => 'openwrt-coovachilli',
            'script' => $script,

            'radius_host' => $radiusHost,

            'wireguard_enabled' =>
                $wireguardEnabled,

            'wireguard_address' =>
                $wgAddress,

            'guest_network' =>
                $guestNetwork,

            'guest_bridge' =>
                'br-hotfii',

            'uam_listen' =>
                $uamListen,

            'uam_port' =>
                $uamPort,

            'external_portal_url' =>
                route('portal.show', $device),
        ];
    }

    public function provision(NetworkDevice $device): array
    {
        return [
            'status' => 'script_ready',
            'configuration' =>
                $this->provisioning($device),
        ];
    }

    public function planAttributes(
        AccessPlan $plan
    ): array {
        return array_filter([
            'Session-Timeout' =>
                $plan->duration_minutes
                    ? $plan->duration_minutes * 60
                    : null,

            'Simultaneous-Use' =>
                $plan->simultaneous_use,

            'WISPr-Bandwidth-Max-Up' =>
                $plan->upload_kbps
                    ? $plan->upload_kbps * 1000
                    : null,

            'WISPr-Bandwidth-Max-Down' =>
                $plan->download_kbps
                    ? $plan->download_kbps * 1000
                    : null,

            'CoovaChilli-Max-Total-Octets' =>
                $plan->data_limit_bytes,
        ], fn ($value) => $value !== null);
    }

    public function tests(NetworkDevice $device): array
    {
        $management =
            $device->management_config ?? [];

        $nas = DB::table('nas')
            ->where(
                'network_device_id',
                $device->id
            )
            ->first();

        $configured =
            filled(
                $management['wireguard_address']
                ?? null
            )
            && filled(
                $management['uam_secret']
                ?? null
            )
            && $nas;

        return [
            [
                'key' => 'configuration',
                'status' =>
                    $configured
                        ? 'passed'
                        : 'pending',
                'message' =>
                    $configured
                        ? 'OpenWrt CoovaChilli, NAS and HotFii management configuration is ready.'
                        : 'OpenWrt HotFii configuration is incomplete.',
            ],

            $this->testManagementConnection($device),
            $this->testRadiusAuthentication($device),
            $this->testAccounting($device),
            $this->testCaptivePortal($device),

            [
                'key' => 'session_tracking',
                'status' => 'pending',
                'message' =>
                    'Waiting for CoovaChilli accounting traffic to create a HotFii session.',
            ],
        ];
    }

    public function disconnect(
        HotspotSession $session
    ): bool {
        $device =
            $session->networkDevice;

        $management =
            $device?->management_config
            ?? [];

        $target =
            $management['wireguard_address']
            ?? $device?->management_address;

        if (
            ! $device
            || ! $target
            || ! $session->radius_username
        ) {
            return false;
        }

        $host = preg_replace(
            '/\/\d+$/',
            '',
            trim((string) $target)
        );

        $clean = static fn (?string $value) =>
            str_replace(
                ["\r", "\n", '"'],
                ['', '', '\"'],
                (string) $value
            );

        $lines = [
            'User-Name = "'.
                $clean(
                    $session->radius_username
                ).
                '"',
        ];

        if ($session->acct_session_id) {
            $lines[] =
                'Acct-Session-Id = "'.
                $clean(
                    $session->acct_session_id
                ).
                '"';
        }

        if ($session->ip_address) {
            $ip = preg_replace(
                '/\/\d+$/',
                '',
                (string) $session->ip_address
            );

            $lines[] =
                'Framed-IP-Address = '.
                $clean($ip);
        }

        $payload =
            implode(PHP_EOL, $lines).
            PHP_EOL;

        try {
            $result = Process::timeout(10)
                ->input($payload)
                ->run([
                    'radclient',
                    '-x',
                    $host.':'.
                        config(
                            'hotfii.radius.coa_port',
                            3799
                        ),
                    'disconnect',
                    $device->radius_secret,
                ]);

            return
                $result->successful()
                && str_contains(
                    $result->output(),
                    'Disconnect-ACK'
                );

        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function synchronize(
        NetworkDevice $device
    ): array {
        return [
            'status' => 'script_ready',
            'message' =>
                'Re-run the idempotent HotFii OpenWrt provisioning script to synchronize managed configuration.',
        ];
    }
}
