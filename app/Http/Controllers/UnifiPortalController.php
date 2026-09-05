<?php

namespace App\Http\Controllers;

use App\Domain\Enums\RouterVendor;
use App\Events\HotspotSessionUpdated;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use App\Services\Access\AllowanceService;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Network\UnifiNetworkApi;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class UnifiPortalController extends Controller
{
    public function authorize(
        Request $request,
        NetworkDevice $device,
        UnifiNetworkApi $api,
        AllowanceService $allowances,
        NetworkDeviceManager $devices,
    ): RedirectResponse {
        abort_unless(
            $device->vendor === RouterVendor::Unifi,
            422
        );

        $data = $request->validate([
            'voucher' => ['required', 'uuid'],
            'id' => ['required', 'string', 'max:32'],
            'ap' => ['nullable', 'string', 'max:32'],
            'ssid' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:1000'],
        ]);

        $voucher = $device->organization->vouchers()
            ->with('credential.accessPlan', 'batch.accessPlan')
            ->where('uuid', $data['voucher'])
            ->firstOrFail();

        $credential = $voucher->credential;

        if (! $credential || $credential->status !== 'active') {
            return back()->withErrors([
                'unifi' => 'This access credential is not active.',
            ]);
        }

        $config = $device->management_config ?? [];

        $apiKey = $config['api_key'] ?? null;
        $hostId = $config['host_id'] ?? null;
        $siteId = $config['site_id'] ?? null;

        if (! $apiKey || ! $hostId || ! $siteId) {
            return back()->withErrors([
                'unifi' => 'This UniFi site has not been connected to HotFii.',
            ]);
        }

        $macRaw = preg_replace(
            '/[^0-9A-Fa-f]/',
            '',
            $data['id']
        );

        if (strlen($macRaw) !== 12) {
            return back()->withErrors([
                'unifi' => 'UniFi supplied an invalid client MAC address.',
            ]);
        }

        $mac = strtoupper(
            implode(':', str_split($macRaw, 2))
        );

        $allowance = $allowances->forCredential($credential);

        $remainingSeconds = $allowance['remaining_seconds'] ?? null;
        $remainingBytes = $allowance['remaining_bytes'] ?? null;

        if ($remainingSeconds !== null && $remainingSeconds <= 0) {
            return back()->withErrors([
                'unifi' => 'This access plan has no remaining time.',
            ]);
        }

        if ($remainingBytes !== null && $remainingBytes <= 0) {
            return back()->withErrors([
                'unifi' => 'This access plan has no remaining data.',
            ]);
        }

        try {
            $client = $api->clientByMac(
                $apiKey,
                $hostId,
                $siteId,
                $mac,
            );

            if (! $client) {
                return back()->withErrors([
                    'unifi' => 'HotFii could not find this guest on the selected UniFi site.',
                ]);
            }

            $clientId = $client['id']
                ?? $client['clientId']
                ?? null;

            if (! $clientId) {
                return back()->withErrors([
                    'unifi' => 'UniFi returned the guest without a client identifier.',
                ]);
            }

            /*
             * A repeated click for the same active authorization should not
             * create another HotFii session or reset UniFi counters.
             */
            $existing = HotspotSession::query()
                ->where('network_device_id', $device->id)
                ->where('source', 'unifi')
                ->where('external_session_id', (string) $clientId)
                ->where('radius_username', $credential->username)
                ->where('status', 'active')
                ->first();

            if ($existing) {
                return redirect()->route('portal.connected', [
                    'device' => $device,
                    'voucher' => $voucher->uuid,
                    'orig' => $data['url'] ?? null,
                    'mac' => $mac,
                ]);
            }

            $plan = $credential->accessPlan
                ?: $voucher->batch?->accessPlan;

            $limits = [
                'time_minutes' => $remainingSeconds !== null
                    ? (int) ceil($remainingSeconds / 60)
                    : null,

                'data_mb' => $remainingBytes !== null
                    ? (int) ceil($remainingBytes / 1048576)
                    : null,

                'download_kbps' => $plan?->download_kbps,
                'upload_kbps' => $plan?->upload_kbps,
            ];

            $result = $api->authorizeGuest(
                $apiKey,
                $hostId,
                $siteId,
                (string) $clientId,
                $limits,
            );

            $granted = data_get(
                $result,
                'grantedAuthorization',
                []
            );

            $revoked = data_get(
                $result,
                'revokedAuthorization'
            );

            /*
             * UniFi cancels an existing guest authorization when a new one
             * is granted. Mirror that transition in HotFii.
             */
            $previousSessions = HotspotSession::query()
                ->where('network_device_id', $device->id)
                ->where('source', 'unifi')
                ->where('external_session_id', (string) $clientId)
                ->where('status', 'active')
                ->get();

            foreach ($previousSessions as $previous) {
                $revokedUsage = data_get(
                    $revoked,
                    'usage',
                    []
                );

                $previous->update([
                    'status' => 'stopped',
                    'input_bytes' => (int) (
                        $revokedUsage['txBytes']
                        ?? $previous->input_bytes
                    ),
                    'output_bytes' => (int) (
                        $revokedUsage['rxBytes']
                        ?? $previous->output_bytes
                    ),
                    'stopped_at' => now(),
                    'terminate_cause' => 'Reauthorized',
                ]);

                HotspotSessionUpdated::dispatch(
                    $previous->refresh()
                );
            }

            $usage = data_get($granted, 'usage', []);

            $expiresAt = data_get(
                $granted,
                'expiresAt'
            );

            $session = HotspotSession::create([
                'organization_id' => $device->organization_id,
                'network_device_id' => $device->id,
                'customer_id' => $credential->customer_id,
                'access_plan_id' => $credential->access_plan_id,

                'source' => 'unifi',
                'radius_username' => $credential->username,
                'external_session_id' => (string) $clientId,

                'session_meta' => [
                    'client_id' => (string) $clientId,
                    'host_id' => $hostId,
                    'site_id' => $siteId,
                    'ap_mac' => $data['ap'] ?? null,
                    'ssid' => $data['ssid'] ?? null,
                    'authorization' => $granted,
                    'last_synced_at' => now()->toIso8601String(),
                ],

                'mac_address' => $mac,
                'ip_address' => $client['ipAddress'] ?? null,
                'status' => 'active',

                'input_bytes' => (int) (
                    $usage['txBytes'] ?? 0
                ),

                'output_bytes' => (int) (
                    $usage['rxBytes'] ?? 0
                ),

                'started_at' => now(),

                'expires_at' => $expiresAt
                    ? Carbon::parse($expiresAt)
                    : (
                        $remainingSeconds !== null
                            ? now()->addSeconds($remainingSeconds)
                            : $credential->expires_at
                    ),
            ]);

        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'unifi' => 'UniFi guest authorization failed. Please try again.',
            ]);
        }

        $credential->update([
            'last_used_at' => now(),
        ]);

        $credential->customer?->update([
            'last_authenticated_at' => now(),
        ]);

        $devices->markEvidence(
            $device,
            'captive_portal',
            'A UniFi guest reached and completed the HotFii portal.',
            [
                'mac' => $mac,
                'ssid' => $data['ssid'] ?? null,
                'session' => $session->uuid,
            ],
        );

        $devices->markEvidence(
            $device,
            'api_authorization',
            'UniFi accepted a HotFii guest authorization.',
            [
                'session' => $session->uuid,
                'mac' => $mac,
            ],
        );

        $devices->markEvidence(
            $device,
            'session_tracking',
            'HotFii created and synchronized a UniFi guest session.',
            [
                'session' => $session->uuid,
            ],
        );

        HotspotSessionUpdated::dispatch($session);

        return redirect()->route('portal.connected', [
            'device' => $device,
            'voucher' => $voucher->uuid,
            'orig' => $data['url'] ?? null,
            'mac' => $mac,
        ]);
    }
}
