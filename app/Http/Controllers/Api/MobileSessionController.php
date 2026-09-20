<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MobileSessionController extends Controller
{
    public function store(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
            'device_id' => ['required', 'string', 'max:128'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The supplied credentials are incorrect.',
            ]);
        }

        $organizations = $this->organizations($user);

        if ($organizations->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => 'This account does not belong to a HotFii organization.',
            ]);
        }

        if ($user->two_factor_confirmed_at) {
            if (empty($data['two_factor_code'])) {
                return response()->json([
                    'two_factor_required' => true,
                    'message' => 'Enter the code from your authenticator app or a recovery code.',
                ], Response::HTTP_ACCEPTED)->header('Cache-Control', 'no-store, private');
            }
            if (! $twoFactor->verifyOrConsumeRecoveryCode($user, $data['two_factor_code'])) {
                throw ValidationException::withMessages([
                    'two_factor_code' => 'The authenticator or recovery code is invalid.',
                ]);
            }
        }

        $tokenName = $this->tokenName($data['device_name'], $data['device_id']);
        $user->tokens()->where('name', $tokenName)->delete();
        $expiresAt = now()->addDays(max(1, config('mobile.token_ttl_days')));
        $token = $user->createToken($tokenName, ['mobile'], $expiresAt);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                ...$this->sessionData($user, $organizations),
            ],
        ], Response::HTTP_CREATED)->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->sessionData($user, $this->organizations($user)),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request): Response
    {
        $tokenName = (string) $request->user()->currentAccessToken()?->name;
        if (preg_match('/^android:.*:([a-f0-9]{16})$/', $tokenName, $matches)) {
            $request->user()->mobileDevices()
                ->where('device_identifier_hash', 'like', $matches[1].'%')
                ->delete();
        }
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function sessionData(User $user, Collection $organizations): array
    {
        return [
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            ],
            'organizations' => $organizations->map(fn (Organization $organization) => [
                'id' => $organization->uuid,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $organization->pivot->role,
                'mode' => $organization->mode->value,
                'status' => $organization->status->value,
                'currency' => $organization->currency,
                'timezone' => $organization->timezone,
                'permissions' => $this->permissions($organization->pivot->role),
            ])->values(),
            'default_organization_id' => $organizations->first()?->uuid,
        ];
    }

    private function organizations(User $user): Collection
    {
        return $user->organizations()
            ->orderBy('name')
            ->get();
    }

    private function tokenName(string $deviceName, string $deviceId): string
    {
        return sprintf(
            'android:%s:%s',
            mb_substr(trim($deviceName), 0, 80),
            substr(hash('sha256', $deviceId), 0, 16),
        );
    }

    private function permissions(string $role): array
    {
        return match ($role) {
            'owner', 'manager' => ['manage_plans', 'manage_vouchers', 'create_vouchers', 'record_cash', 'manage_network', 'disconnect_sessions'],
            'agent' => ['create_vouchers', 'record_cash'],
            'technician' => ['manage_network', 'disconnect_sessions'],
            default => [],
        };
    }
}
