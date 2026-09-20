<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MobileDeviceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'device_name' => ['required', 'string', 'max:100'],
            'push_token' => ['required', 'string', 'max:4096'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'os_version' => ['nullable', 'string', 'max:40'],
        ]);
        $deviceHash = hash('sha256', $data['device_id']);
        $tokenHash = hash('sha256', $data['push_token']);

        MobileDevice::where('push_token_hash', $tokenHash)
            ->where(fn ($query) => $query
                ->where('user_id', '!=', $request->user()->id)
                ->orWhere('device_identifier_hash', '!=', $deviceHash))
            ->update(['push_token' => null, 'push_token_hash' => null]);

        $device = MobileDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_identifier_hash' => $deviceHash],
            [
                'platform' => 'android',
                'name' => $data['device_name'],
                'push_token' => $data['push_token'],
                'push_token_hash' => $tokenHash,
                'app_version' => $data['app_version'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['data' => [
            'id' => $device->uuid,
            'name' => $device->name,
            'registered' => true,
        ]]);
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['device_id' => ['required', 'string', 'max:128']]);
        $request->user()->mobileDevices()
            ->where('device_identifier_hash', hash('sha256', $data['device_id']))
            ->delete();

        return response()->noContent();
    }
}
