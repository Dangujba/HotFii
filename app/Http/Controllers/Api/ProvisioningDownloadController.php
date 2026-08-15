<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningToken;
use App\Services\Network\RouterAdapterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProvisioningDownloadController extends Controller
{
    public function __invoke(string $token, RouterAdapterRegistry $adapters): Response|JsonResponse
    {
        $record = ProvisioningToken::with('networkDevice')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_if($record->expires_at->isPast(), 410, 'This provisioning link has expired.');
        $record->update(['used_at' => $record->used_at ?? now()]);

        $provisioning = $adapters->byKey($record->networkDevice->adapter)->provisioning($record->networkDevice);
        if (($provisioning['method'] ?? null) === 'script') {
            return response($provisioning['script'], 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response()->json($provisioning)->header('Cache-Control', 'no-store, private');
    }
}