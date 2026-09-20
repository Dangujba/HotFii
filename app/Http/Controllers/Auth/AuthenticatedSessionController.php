<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthentication;

class AuthenticatedSessionController extends Controller
{
    public function create(): View { return view('auth.login'); }
    public function store(Request $request): RedirectResponse
    {
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $user = User::query()->where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are incorrect.']);
        }
        if ($user->two_factor_confirmed_at) {
            $request->session()->regenerate();
            $request->session()->put('login.two_factor', [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.challenge');
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        // The dashboard needs an organization. A platform admin who only does
        // support work belongs to none, so send them to the platform console
        // instead of into a 403.
        return redirect()->intended($user->is_platform_admin && !$user->organizations()->exists() ? route('platform.index') : route('dashboard'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.two_factor')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $pending = $request->session()->get('login.two_factor');
        if (! is_array($pending) || empty($pending['user_id'])) {
            return redirect()->route('login');
        }

        $user = User::query()->findOrFail($pending['user_id']);
        if (! $twoFactor->verifyOrConsumeRecoveryCode($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator or recovery code is invalid.']);
        }

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->forget('login.two_factor');
        $request->session()->regenerate();

        return redirect()->intended($user->is_platform_admin && ! $user->organizations()->exists()
            ? route('platform.index')
            : route('dashboard'));
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
