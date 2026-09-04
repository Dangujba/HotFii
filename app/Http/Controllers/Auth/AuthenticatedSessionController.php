<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View { return view('auth.login'); }
    public function store(Request $request): RedirectResponse
    {
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if(!Auth::attempt($credentials,$request->boolean('remember'))) throw ValidationException::withMessages(['email'=>'The supplied credentials are incorrect.']);
        $request->session()->regenerate();
        // The dashboard needs an organization. A platform admin who only does
        // support work belongs to none, so send them to the platform console
        // instead of into a 403.
        $user=$request->user();
        return redirect()->intended($user->is_platform_admin && !$user->organizations()->exists() ? route('platform.index') : route('dashboard'));
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}