<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MobileTwoFactorController extends Controller
{
    public function store(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $user = $request->user();
        if ($user->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['two_factor' => 'Two-factor authentication is already enabled.']);
        }

        $secret = $twoFactor->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->json(['data' => [
            'secret' => $secret,
            'provisioning_uri' => $twoFactor->provisioningUri($user, $secret),
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! $twoFactor->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid or has expired.']);
        }

        $recoveryCodes = $twoFactor->recoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($recoveryCodes),
        ])->save();

        return response()->json(['data' => [
            'enabled' => true,
            'recovery_codes' => $recoveryCodes,
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'The supplied password is incorrect.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->noContent();
    }
}
