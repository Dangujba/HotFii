<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Events\NetworkDeviceStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use App\Services\Network\NetworkDeviceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkDeviceHeartbeatController extends Controller
{
    public function __invoke(
        Request $request,
        NetworkDevice $device,
        NetworkDeviceManager $manager
    ): JsonResponse {
        abort_unless(
            hash_equals(
                $device->radius_secret,
                (string) $request->header('X-HotFii-Secret')
            ),
            401
        );

        $data = $request->validate([
            'firmware_version' => [
                'nullable',
                'string',
                'max:100',
            ],

            'health' => [
                'nullable',
                'array',
            ],

            'clients' => [
                'nullable',
                'array',
                'max:100',
            ],

            'clients.*.mac' => [
                'required_with:clients',
                'string',
                'max:64',
            ],

            'clients.*.name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $wasOnline =
            $device->status ===
            NetworkDeviceStatus::Online;

        $device->update([
            'status' =>
                NetworkDeviceStatus::Online,

            'firmware_version' =>
                $data['firmware_version']
                ?? $device->firmware_version,

            'health' =>
                $data['health']
                ?? $device->health,

            'last_heartbeat_at' =>
                now(),
        ]);

        /*
         * Managed routers may include currently connected
         * client hostnames in their signed heartbeat.
         *
         * Match hostname metadata against live sessions
         * using router + normalized client MAC.
         */
        foreach (($data['clients'] ?? []) as $client) {
            $name =
                isset($client['name'])
                && is_string($client['name'])
                    ? trim($client['name'])
                    : '';

            $mac =
                isset($client['mac'])
                && is_string($client['mac'])
                    ? strtolower(
                        preg_replace(
                            '/[^0-9a-f]/i',
                            '',
                            $client['mac']
                        )
                    )
                    : '';

            if (
                $name === ''
                || strlen($mac) !== 12
            ) {
                continue;
            }

            HotspotSession::query()
                ->where(
                    'network_device_id',
                    $device->id
                )
                ->whereIn(
                    'status',
                    [
                        'active',
                        'disconnect_pending',
                    ]
                )
                ->whereRaw(
                    "LOWER(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    mac_address,
                                    ':',
                                    ''
                                ),
                                '-',
                                ''
                            ),
                            '.',
                            ''
                        )
                    ) = ?",
                    [$mac]
                )
                ->update([
                    'client_name' =>
                        mb_substr(
                            $name,
                            0,
                            255
                        ),
                ]);
        }

        $manager->markEvidence(
            $device,
            'heartbeat',
            'A signed heartbeat was received.',
            $data['health'] ?? []
        );

        if (! $wasOnline) {
            NetworkDeviceStatusChanged::dispatch(
                $device->refresh()
            );
        }

        return response()->json([
            'status' =>
                'ok',

            'server_time' =>
                now()->toIso8601String(),

            'next_heartbeat_seconds' =>
                60,
        ]);
    }
}
