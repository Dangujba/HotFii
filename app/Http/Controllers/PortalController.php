<?php

namespace App\Http\Controllers;

use App\Domain\Enums\RouterVendor;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use App\Services\Access\AllowanceService;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Vouchers\VoucherService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PortalController extends Controller
{
    public function show(
        Request $request,
        NetworkDevice $device,
        NetworkDeviceManager $manager
    ): View {
        $portalContext = $this->portalContext($request, $device);

        $portalMac =
            $portalContext['mac']
            ?? $portalContext['id']
            ?? $portalContext['clientMac']
            ?? null;

        $mikrotikRedirect =
            filled($portalContext['link_login'] ?? null)
            && filled($portalContext['mac'] ?? null);

        $unifiRedirect =
            filled($portalContext['id'] ?? null)
            && filled($portalContext['ssid'] ?? null);

        $omadaRedirect =
            $device->vendor === RouterVendor::Omada
            && filled($portalContext['clientMac'] ?? null)
            && filled($portalContext['target'] ?? null);

        if ($mikrotikRedirect || $unifiRedirect || $omadaRedirect) {
            $manager->markEvidence(
                $device,
                'captive_portal',
                'A router redirect reached the HotFii captive portal.',
                [
                    'mac' => $portalMac,
                    'ssid' =>
                        $portalContext['ssid']
                        ?? $portalContext['ssidName']
                        ?? null,
                    'ap' =>
                        $portalContext['ap']
                        ?? $portalContext['apMac']
                        ?? null,
                    'gateway' =>
                        $portalContext['gatewayMac']
                        ?? null,
                    'vendor' => $device->vendor->value,
                ],
            );
        }

        return view('portal.show', [
            'device' => $device->load('organization', 'location'),

            'plans' => $device->organization
                ->accessPlans()
                ->where('is_active', true)
                ->orderBy('price_kobo')
                ->get(),

            'canBuyOnline' =>
                $device->organization->canCollectLivePayments(),

            'portalContext' => $portalContext,
        ]);
    }

    public function redeem(
        Request $request,
        NetworkDevice $device,
        VoucherService $service
    ): RedirectResponse {
        /*
         * Normalise vendor-specific aliases first so that the same
         * canonical field names are carried through the voucher flow.
         */
        $request->merge(
            $this->portalContext($request, $device)
        );

        $data = $request->validate([
            'voucher_code' => [
                'required',
                'string',
                'max:64',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:32',
            ],

            // MikroTik
            'link_login' => [
                'nullable',
                'url:http,https',
                'max:500',
            ],

            'link_orig' => [
                'nullable',
                'url:http,https',
                'max:1000',
            ],

            'mac' => [
                'nullable',
                'string',
                'max:64',
            ],

            'ip' => [
                'nullable',
                'ip',
            ],

            // UniFi
            'id' => [
                'nullable',
                'string',
                'max:64',
            ],

            'ap' => [
                'nullable',
                'string',
                'max:64',
            ],

            'ssid' => [
                'nullable',
                'string',
                'max:255',
            ],

            'url' => [
                'nullable',
                'url:http,https',
                'max:2000',
            ],

            // Omada
            'target' => [
                'nullable',
                'string',
                'max:255',
            ],

            'targetPort' => [
                'nullable',
                'integer',
                'between:1,65535',
            ],

            'scheme' => [
                'nullable',
                'in:http,https',
            ],

            'clientMac' => [
                'nullable',
                'string',
                'max:64',
            ],

            'clientIp' => [
                'nullable',
                'ip',
            ],

            'apMac' => [
                'nullable',
                'string',
                'max:64',
            ],

            'gatewayMac' => [
                'nullable',
                'string',
                'max:64',
            ],

            'ssidName' => [
                'nullable',
                'string',
                'max:255',
            ],

            'vid' => [
                'nullable',
                'integer',
                'between:0,4094',
            ],

            'radioId' => [
                'nullable',
                'integer',
                'between:0,8',
            ],

            'authType' => [
                'nullable',
                'integer',
                'in:2,8',
            ],

            'originUrl' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        try {
            $voucher = $service->redeem(
                $device->organization,
                $data['voucher_code'],
                $data['phone'] ?? null
            );
        } catch (RuntimeException|ModelNotFoundException $exception) {
            return back()
                ->withErrors([
                    'voucher_code' =>
                        $exception instanceof ModelNotFoundException
                            ? 'The voucher code was not found.'
                            : $exception->getMessage(),
                ])
                ->withInput();
        }

        return redirect()->route('portal.status', [
            'device' => $device,
            'voucher' => $voucher->uuid,

            ...collect($data)
                ->only([
                    // MikroTik
                    'link_login',
                    'link_orig',
                    'mac',
                    'ip',

                    // UniFi
                    'id',
                    'ap',
                    'ssid',
                    'url',

                    // Omada
                    'target',
                    'targetPort',
                    'scheme',
                    'clientMac',
                    'clientIp',
                    'apMac',
                    'gatewayMac',
                    'ssidName',
                    'vid',
                    'radioId',
                    'authType',
                    'originUrl',
                ])
                ->filter(
                    fn ($value) =>
                        $value !== null
                        && $value !== ''
                )
                ->all(),
        ]);
    }

    public function mikrotikLogin(
        NetworkDevice $device
    ): \Illuminate\Http\Response {
        $portalUrl = route('portal.show', $device);

        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connecting to HotFii</title>
</head>
<body>
    <p>Connecting to HotFii…</p>

    <form id="hotfii-redirect" method="get" action="__PORTAL_URL__">
        <input type="hidden" name="link_login" value="$(link-login-only)">
        <input type="hidden" name="link_orig" value="$(link-orig)">
        <input type="hidden" name="mac" value="$(mac)">
        <input type="hidden" name="ip" value="$(ip)">

        <noscript>
            <button type="submit">Continue to HotFii</button>
        </noscript>
    </form>

    <script>
        document.getElementById('hotfii-redirect').submit();
    </script>
</body>
</html>
HTML;

        $html = str_replace(
            '__PORTAL_URL__',
            htmlspecialchars(
                $portalUrl,
                ENT_QUOTES,
                'UTF-8'
            ),
            $html
        );

        return response($html, 200, [
            'Content-Type' =>
                'text/html; charset=UTF-8',

            'Cache-Control' =>
                'no-store, no-cache, must-revalidate',
        ]);
    }

    public function connected(
        NetworkDevice $device,
        Request $request
    ) {
        $voucher = $device->organization
            ->vouchers()
            ->with('credential')
            ->where('uuid', $request->query('voucher'))
            ->first();

        $config =
            $device->management_config ?? [];

        $nasIp =
            is_array($config)
                ? ($config['wireguard_address'] ?? null)
                : null;

        if ($nasIp) {
            $nasIp = preg_replace(
                '/\/\d+$/',
                '',
                $nasIp
            );
        }

        if (! $nasIp) {
            $nasIp = DB::table('nas')
                ->where(
                    'network_device_id',
                    $device->id
                )
                ->value('nasname');
        }

        $mac = $request->query('mac');

        $connected = false;

        if (
            $device->vendor === RouterVendor::Unifi
            && $voucher?->credential
        ) {
            $query = HotspotSession::query()
                ->where(
                    'network_device_id',
                    $device->id
                )
                ->where('source', 'unifi')
                ->where(
                    'radius_username',
                    $voucher->credential->username
                )
                ->where('status', 'active')
                ->where(
                    'started_at',
                    '>=',
                    now()->subMinutes(5)
                );

            if ($mac) {
                $normalizedMac =
                    strtolower(
                        preg_replace(
                            '/[^0-9a-f]/i',
                            '',
                            $mac
                        )
                    );

                $query->whereRaw(
                    "LOWER(
                        REPLACE(
                            REPLACE(
                                REPLACE(mac_address, ':', ''),
                                '-', ''
                            ),
                            '.', ''
                        )
                    ) = ?",
                    [$normalizedMac]
                );
            }

            $connected = $query->exists();

        } elseif (
            $voucher?->credential
            && $nasIp
        ) {
            $query = DB::table('radacct')
                ->where(
                    'username',
                    $voucher->credential->username
                )
                ->where(
                    'nasipaddress',
                    $nasIp
                )
                ->whereNull('acctstoptime')
                ->whereRaw("
                    (
                        acctstarttime >=
                            CURRENT_TIMESTAMP - INTERVAL '3 minutes'

                        OR acctupdatetime >=
                            CURRENT_TIMESTAMP - INTERVAL '3 minutes'
                    )
                ");

            /*
             * Different vendors format Calling-Station-Id differently:
             * AA:BB..., AA-BB..., or sometimes dotted.
             */
            if ($mac) {
                $normalizedMac =
                    strtolower(
                        preg_replace(
                            '/[^0-9a-f]/i',
                            '',
                            $mac
                        )
                    );

                $query->whereRaw(
                    "LOWER(
                        REPLACE(
                            REPLACE(
                                REPLACE(callingstationid, ':', ''),
                                '-', ''
                            ),
                            '.', ''
                        )
                    ) = ?",
                    [$normalizedMac]
                );
            }

            $connected = $query->exists();
        }

        if ($request->boolean('check')) {
            return response()->json([
                'connected' => $connected,
            ]);
        }

        $originalUrl =
            $request->query('orig');

        if (
            ! is_string($originalUrl)
            || ! preg_match(
                '#^https?://#i',
                $originalUrl
            )
        ) {
            $originalUrl =
                'https://www.google.com';
        }

        return view('portal.connected', [
            'device' => $device,
            'voucher' => $voucher,
            'connected' => $connected,
            'originalUrl' => $originalUrl,
        ]);
    }

    public function status(
        NetworkDevice $device,
        Request $request,
        AllowanceService $allowances
    ): View {
        $voucher = $device->organization
            ->vouchers()
            ->with(
                'credential.accessPlan',
                'batch.accessPlan'
            )
            ->where(
                'uuid',
                $request->query('voucher')
            )
            ->first();

        $portalContext =
            $this->portalContext(
                $request,
                $device
            );

        return view('portal.status', [
            'device' => $device,
            'voucher' => $voucher,

            'portalContext' =>
                $portalContext,

            'allowance' =>
                $allowances->forCredential(
                    $voucher?->credential
                ),

            /*
             * IMPORTANT:
             * Never build the credential POST destination from the
             * untrusted ?target= value supplied by the browser.
             *
             * Use the operator-verified controller endpoint saved
             * in this device's encrypted management_config.
             */
            'omadaBrowserAuthUrl' =>
                $this->omadaBrowserAuthUrl(
                    $device
                ),
        ]);
    }

    /**
     * Normalise captive portal parameters from each supported vendor.
     */
    private function portalContext(
        Request $request,
        NetworkDevice $device
    ): array {
        $context = [];

        foreach ([
            // MikroTik
            'link_login',
            'link_orig',
            'mac',
            'ip',

            // UniFi
            'id',
            'ap',
            'ssid',
            'url',
        ] as $key) {
            $value = $request->input($key);

            if (
                is_scalar($value)
                && $value !== ''
            ) {
                $context[$key] = $value;
            }
        }

        if (
            $device->vendor !== RouterVendor::Omada
        ) {
            return $context;
        }

        /*
         * Omada documentation and older controller releases are not
         * completely consistent in capitalisation, so accept aliases
         * and expose one canonical representation to the views.
         */
        $aliases = [
            'target' => [
                'target',
            ],

            'targetPort' => [
                'targetPort',
                'targetport',
            ],

            'scheme' => [
                'scheme',
            ],

            'clientMac' => [
                'clientMac',
                'clientMAC',
            ],

            'clientIp' => [
                'clientIp',
                'clientIP',
            ],

            'apMac' => [
                'apMac',
            ],

            'gatewayMac' => [
                'gatewayMac',
                'GatewayMac',
            ],

            'ssidName' => [
                'ssidName',
            ],

            'vid' => [
                'vid',
            ],

            'radioId' => [
                'radioId',
            ],

            'authType' => [
                'authType',
            ],

            'originUrl' => [
                'originUrl',
                'originalUrl',
                'redirectUrl',
            ],
        ];

        foreach (
            $aliases
            as $canonical => $candidateKeys
        ) {
            foreach (
                $candidateKeys
                as $candidate
            ) {
                $value =
                    $request->input($candidate);

                if (
                    is_scalar($value)
                    && $value !== ''
                ) {
                    $context[$canonical] =
                        $value;

                    break;
                }
            }
        }

        /*
         * HotFii's Omada implementation uses External RADIUS.
         * TP-Link defines:
         * 2 = External RADIUS
         * 8 = Hotspot RADIUS
         */
        if (
            ! isset($context['authType'])
        ) {
            $context['authType'] = 2;
        }

        return $context;
    }

    /**
     * Return the trusted Omada browser-auth endpoint.
     *
     * This is intentionally generated from management_config rather
     * than from the browser's ?target= parameter.
     */
    private function omadaBrowserAuthUrl(
        NetworkDevice $device
    ): ?string {
        if (
            $device->vendor !== RouterVendor::Omada
        ) {
            return null;
        }

        $config =
            $device->management_config ?? [];

        if (! is_array($config)) {
            return null;
        }

        $host =
            trim(
                (string) (
                    $config['portal_host']
                    ?? ''
                )
            );

        $scheme =
            strtolower(
                trim(
                    (string) (
                        $config['portal_scheme']
                        ?? 'https'
                    )
                )
            );

        $port =
            (int) (
                $config['portal_port']
                ?? 8843
            );

        if (
            $host === ''
            || ! preg_match(
                '/^[A-Za-z0-9.-]+$/',
                $host
            )
            || ! in_array(
                $scheme,
                ['http', 'https'],
                true
            )
            || $port < 1
            || $port > 65535
        ) {
            return null;
        }

        return sprintf(
            '%s://%s:%d/portal/radius/browserauth',
            $scheme,
            $host,
            $port
        );
    }
}
