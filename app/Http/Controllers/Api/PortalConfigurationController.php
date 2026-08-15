<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use Illuminate\Http\JsonResponse;

class PortalConfigurationController extends Controller
{
    public function __invoke(NetworkDevice $device): JsonResponse
    {
        $organization = $device->organization;

        return response()->json([
            'device' => ['uuid' => $device->uuid, 'name' => $device->name, 'status' => $device->status->value],
            'organization' => [
                'name' => $organization->name,
                'mode' => $organization->mode->value,
                'branding' => $organization->branding,
            ],
            'plans' => $organization->accessPlans()
                ->where('is_active', true)
                ->orderBy('price_kobo')
                ->get()
                ->map(fn ($plan) => [
                    'uuid' => $plan->uuid,
                    'name' => $plan->name,
                    'access_type' => $plan->access_type,
                    'price_kobo' => $plan->price_kobo,
                    'duration_minutes' => $plan->duration_minutes,
                    'data_limit_bytes' => $plan->data_limit_bytes,
                    'download_kbps' => $plan->download_kbps,
                    'upload_kbps' => $plan->upload_kbps,
                ]),
        ])->header('Cache-Control', 'public, max-age=30');
    }
}