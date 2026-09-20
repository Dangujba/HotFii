<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class MobileDeviceSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->getKey();
        $devices = $request->user()->mobileDevices()->get()->keyBy(
            fn ($device) => substr((string) $device->device_identifier_hash, 0, 16),
        );
        $sessions = $request->user()->tokens()
            ->where('name', 'like', 'android:%')
            ->latest('last_used_at')
            ->latest('created_at')
            ->get()
            ->map(function (PersonalAccessToken $token) use ($currentId, $devices): array {
                preg_match('/^android:(.*):([a-f0-9]{16})$/', $token->name, $matches);
                $device = isset($matches[2]) ? $devices->get($matches[2]) : null;

                return [
                    'id' => $this->publicId($token),
                    'name' => $device?->name ?? ($matches[1] ?? 'Android device'),
                    'platform' => $device?->platform ?? 'android',
                    'app_version' => $device?->app_version,
                    'os_version' => $device?->os_version,
                    'last_seen_at' => $device?->last_seen_at?->toIso8601String()
                        ?? $token->last_used_at?->toIso8601String()
                        ?? $token->created_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'is_current' => (int) $token->getKey() === (int) $currentId,
                ];
            })->values();

        return response()->json(['data' => ['sessions' => $sessions]])
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, string $session): Response
    {
        $token = $request->user()->tokens()
            ->where('name', 'like', 'android:%')
            ->get()
            ->first(fn (PersonalAccessToken $candidate) => hash_equals($this->publicId($candidate), $session));
        abort_unless($token, 404);
        abort_if(
            (int) $token->getKey() === (int) $request->user()->currentAccessToken()?->getKey(),
            422,
            'Use Sign out to end the session on this device.',
        );
        if (preg_match('/^android:.*:([a-f0-9]{16})$/', $token->name, $matches)) {
            $request->user()->mobileDevices()
                ->where('device_identifier_hash', 'like', $matches[1].'%')
                ->delete();
        }
        $token->delete();

        return response()->noContent();
    }

    private function publicId(PersonalAccessToken $token): string
    {
        return substr(hash_hmac('sha256', (string) $token->getKey(), (string) config('app.key')), 0, 32);
    }
}
