<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationContextController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->organizations()->whereKey($organization->id)->exists(), 403);
        $request->session()->forget('impersonated_organization_id');
        $request->session()->put('current_organization_id', $organization->id);

        return redirect()->route('dashboard');
    }
}