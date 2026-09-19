<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MobileSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
            'device_id' => ['required', 'string', 'max:128'],
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
            'owner', 'manager' => ['manage_plans', 'manage_vouchers', 'create_vouchers', 'record_cash', 'disconnect_sessions'],
            'agent' => ['create_vouchers', 'record_cash'],
            'technician' => ['disconnect_sessions'],
            default => [],
        };
    }
}
