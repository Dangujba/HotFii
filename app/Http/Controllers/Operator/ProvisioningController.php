<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\ProvisioningToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProvisioningController extends Controller
{
    public function __invoke(Request $request, NetworkDevice $device): RedirectResponse
    {
        abort_unless($device->organization_id === $request->attributes->get('organization')->id, 404);

        $plainToken = Str::random(64);
        ProvisioningToken::create([
            'network_device_id' => $device->id,
            'created_by' => $request->user()->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        return back()->with('success', 'A provisioning link valid for 15 minutes has been generated.')
            ->with('provisioning_url', route('api.v1.provisioning.download', ['token' => $plainToken]));
    }
}