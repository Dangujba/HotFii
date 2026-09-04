<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Services\Network\WireGuardPeerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NetworkDeviceWireGuardEnrollController extends Controller
{
    public function __invoke(
        Request $request,
        NetworkDevice $device,
        WireGuardPeerManager $wireguard,
    ): JsonResponse {
        abort_unless(
            hash_equals(
                $device->radius_secret,
                (string) $request->header('X-HotFii-Secret')
            ),
            401
        );

        abort_unless($device->vendor === RouterVendor::Mikrotik, 422);

        $data = $request->validate([
            'public_key' => ['required', 'string', 'max:100'],
        ]);

        $publicKey = trim($data['public_key']);
        $decoded = base64_decode($publicKey, true);

        abort_unless($decoded !== false && strlen($decoded) === 32, 422, 'Invalid WireGuard public key.');

        $management = $device->management_config ?? [];
        $address = $management['wireguard_address'] ?? null;

        abort_unless(is_string($address) && $address !== '', 422, 'WireGuard address is not assigned.');

        $result = $wireguard->enroll($publicKey, $address);

        $management['wireguard_public_key'] = $publicKey;
        $management['wireguard_enrolled_at'] = now()->toIso8601String();

        DB::transaction(function () use ($device, $management, $address) {
            $device->update([
                'management_config' => $management,
            ]);

            DB::table('nas')
                ->where('network_device_id', $device->id)
                ->update([
                    'nasname' => Str::before($address, '/'),
                    'shortname' => $device->nas_identifier,
                    'secret' => $device->radius_secret,
                ]);
        });

        return response()->json([
            'status' => 'enrolled',
            'address' => $result['address'] ?? $address,
            'radius_host' => config('hotfii.wireguard.server_address'),
            'heartbeat_seconds' => 60,
        ]);
    }
}
