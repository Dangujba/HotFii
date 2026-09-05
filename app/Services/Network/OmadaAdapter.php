<?php

namespace App\Services\Network;

use App\Models\AccessPlan;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

class OmadaAdapter extends GenericRadiusAdapter
{
    public function key(): string
    {
        return 'tp-link-omada';
    }

    public function capabilities(): array
    {
        return [
            'radius_auth',
            'radius_accounting',
            'captive_portal',
            'external_portal',
            'coa_disconnect',
            'time_limits',
            'simultaneous_use',
            'session_tracking',
        ];
    }

    public function provisioning(
        NetworkDevice $device
    ): array {
        $base = parent::provisioning($device);

        return [
            ...$base,

            'method' => 'manual',
            'integration' => 'omada-radius-portal',

            'radius_host' =>
                config('hotfii.radius.host'),

            'authentication_port' =>
                config('hotfii.radius.auth_port'),

            'accounting_port' =>
                config('hotfii.radius.accounting_port'),

            'coa_port' =>
                config('hotfii.radius.coa_port'),

            'nas_identifier' =>
                $device->nas_identifier,

            'radius_secret' =>
                $device->radius_secret,

            'external_portal_url' =>
                route('portal.show', $device),

            'authentication_mode' => 'PAP',

            'browserauth_path' =>
                '/portal/radius/browserauth',

            'walled_garden' => [
                parse_url(
                    config('app.url'),
                    PHP_URL_HOST
                ),

                'checkout.paystack.com',
                'api.paystack.co',
            ],
        ];
    }

    public function planAttributes(
        AccessPlan $plan
    ): array {
        return array_filter(
            [
                'Session-Timeout' =>
                    $plan->duration_minutes
                        ? $plan->duration_minutes * 60
                        : null,

                'Simultaneous-Use' =>
                    $plan->simultaneous_use,
            ],

            fn ($value) =>
                $value !== null
        );
    }

    public function disconnect(
        HotspotSession $session
    ): bool {
        $device =
            $session->networkDevice;

        if (
            ! $session->acct_session_id
            || ! $session->radius_username
            || ! $device?->management_address
        ) {
            return false;
        }

        $target =
            trim(
                (string) $device->management_address
            );

        $host = parse_url(
            str_contains($target, '://')
                ? $target
                : 'udp://'.$target,
            PHP_URL_HOST
        );

        if (! $host) {
            return false;
        }

        $nasIp = DB::table('nas')
            ->where(
                'network_device_id',
                $device->id
            )
            ->value('nasname');

        $clean = fn (?string $value) =>
            str_replace(
                [
                    "\r",
                    "\n",
                    '"',
                ],
                [
                    '',
                    '',
                    '\"',
                ],
                (string) $value
            );

        $payload =
            'User-Name = "'
            .$clean($session->radius_username)
            .'"'
            .PHP_EOL

            .'Acct-Session-Id = "'
            .$clean($session->acct_session_id)
            .'"'
            .PHP_EOL;

        if (
            $nasIp
            && filter_var(
                $nasIp,
                FILTER_VALIDATE_IP
            )
        ) {
            $payload .=
                'NAS-IP-Address = '
                .$clean($nasIp)
                .PHP_EOL;
        }

        $result = Process::timeout(10)
            ->input($payload)
            ->run([
                'radclient',
                '-x',
                $host.':'.config(
                    'hotfii.radius.coa_port'
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
    }

    public function tests(
        NetworkDevice $device
    ): array {
        $config =
            $device->management_config ?? [];

        $radiusSource =
            is_array($config)
                ? ($config['radius_source_ip'] ?? null)
                : null;

        $portalHost =
            is_array($config)
                ? ($config['portal_host'] ?? null)
                : null;

        $portalPort =
            is_array($config)
                ? ($config['portal_port'] ?? null)
                : null;

        $portalScheme =
            is_array($config)
                ? ($config['portal_scheme'] ?? null)
                : null;

        $nasMatches =
            filled($radiusSource)
            && DB::table('nas')
                ->where(
                    'network_device_id',
                    $device->id
                )
                ->where(
                    'nasname',
                    $radiusSource
                )
                ->where(
                    'type',
                    'omada'
                )
                ->exists();

        $configurationReady =
            filter_var(
                $radiusSource,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
            && filled($portalHost)
            && in_array(
                $portalScheme,
                ['http', 'https'],
                true
            )
            && is_numeric($portalPort)
            && (int) $portalPort >= 1
            && (int) $portalPort <= 65535
            && $nasMatches;

        $hasTrackedSession =
            HotspotSession::query()
                ->where(
                    'network_device_id',
                    $device->id
                )
                ->where(
                    'source',
                    'omada'
                )
                ->exists();

        return [
            [
                'key' => 'configuration',

                'status' =>
                    $configurationReady
                        ? 'passed'
                        : 'pending',

                'message' =>
                    $configurationReady
                        ? 'Omada RADIUS source IP, controller portal endpoint and NAS registration are configured.'
                        : 'Complete the Omada Controller Connection settings before testing.',
            ],

            $this->testRadiusAuthentication(
                $device
            ),

            $this->testAccounting(
                $device
            ),

            $this->testCaptivePortal(
                $device
            ),

            [
                'key' => 'session_tracking',

                'status' =>
                    $hasTrackedSession
                        ? 'passed'
                        : 'pending',

                'message' =>
                    $hasTrackedSession
                        ? 'HotFii has synchronized an Omada RADIUS session.'
                        : 'Waiting for Omada accounting traffic to create a HotFii session.',
            ],

            [
                'key' => 'coa',
                'status' => 'pending',
                'message' =>
                    'Waiting for a live Omada Disconnect-Request test.',
            ],
        ];
    }

    public function synchronize(
        NetworkDevice $device
    ): array {
        return [
            'status' => 'manual',

            'message' =>
                'Apply the generated HotFii RADIUS and External Portal settings in Omada Controller.',
        ];
    }

    public function normalizeError(
        Throwable $exception
    ): array {
        return [
            'code' => 'omada_error',

            'message' =>
                $exception->getMessage(),

            'retryable' => false,
        ];
    }
}
