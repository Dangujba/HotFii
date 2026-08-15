<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunNetworkDeviceTests;
use App\Models\NetworkDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationTestStatusController extends Controller
{
    public function show(Request $request, NetworkDevice $device): JsonResponse
    {
        $this->authorizeDevice($request, $device);
        $run = $device->tests()->latest('id')->value('run_uuid');

        return response()->json([
            'device_status' => $device->status->value,
            'run' => $run,
            'tests' => $run
                ? $device->tests()->where('run_uuid', $run)->orderBy('id')->get(['test_key', 'status', 'message', 'details', 'checked_at'])
                : [],
        ]);
    }

    public function store(Request $request, NetworkDevice $device): JsonResponse
    {
        $this->authorizeDevice($request, $device);
        RunNetworkDeviceTests::dispatch($device);

        return response()->json(['status' => 'queued'], 202);
    }

    private function authorizeDevice(Request $request, NetworkDevice $device): void
    {
        abort_unless(hash_equals($device->radius_secret, (string) $request->header('X-HotFii-Secret')), 401);
    }
}