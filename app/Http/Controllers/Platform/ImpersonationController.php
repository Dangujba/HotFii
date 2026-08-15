<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => 'support.impersonation.started',
            'subject_type' => Organization::class,
            'subject_id' => $organization->id,
            'ip_address' => $request->ip(),
            'reason' => $data['reason'],
        ]);

        $request->session()->put([
            'impersonated_organization_id' => $organization->id,
            'impersonation_reason' => $data['reason'],
            'impersonation_started_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $organizationId = $request->session()->pull('impersonated_organization_id');
        if ($organizationId) {
            AuditLog::create([
                'organization_id' => $organizationId,
                'user_id' => $request->user()->id,
                'action' => 'support.impersonation.stopped',
                'subject_type' => Organization::class,
                'subject_id' => $organizationId,
                'ip_address' => $request->ip(),
                'reason' => $request->session()->pull('impersonation_reason'),
            ]);
        }

        $request->session()->forget(['impersonation_started_at', 'current_organization_id']);
        return redirect()->route('platform.index');
    }
}