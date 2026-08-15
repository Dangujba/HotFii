<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Events\NetworkDeviceStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Services\Network\NetworkDeviceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkDeviceHeartbeatController extends Controller
{
    public function __invoke(Request $request, NetworkDevice $device, NetworkDeviceManager $manager): JsonResponse
    {
        abort_unless(hash_equals($device->radius_secret, (string) $request->header('X-HotFii-Secret')), 401);

        $data = $request->validate([
            'firmware_version' => ['nullable', 'string', 'max:100'],
            'health' => ['nullable', 'array'],
        ]);

        $wasOnline = $device->status === NetworkDeviceStatus::Online;
        $device->update([
            'status' => NetworkDeviceStatus::Online,
            'firmware_version' => $data['firmware_version'] ?? $device->firmware_version,
            'health' => $data['health'] ?? $device->health,
            'last_heartbeat_at' => now(),
        ]);

        $manager->markEvidence($device, 'heartbeat', 'A signed heartbeat was received.', $data['health'] ?? []);

        if (! $wasOnline) {
            NetworkDeviceStatusChanged::dispatch($device->refresh());
        }

        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'next_heartbeat_seconds' => 60,
        ]);
    }
}