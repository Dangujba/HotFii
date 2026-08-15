<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $impersonatedId = $request->session()->get('impersonated_organization_id');

        if ($user->is_platform_admin && $impersonatedId) {
            $organization = Organization::find($impersonatedId);
        } else {
            $query = $user->organizations();
            $organizationId = $request->session()->get('current_organization_id');
            $organization = $organizationId
                ? (clone $query)->whereKey($organizationId)->first()
                : $query->first();
        }

        abort_unless($organization, 403, 'You do not belong to an organization.');
        $request->session()->put('current_organization_id', $organization->id);
        $request->attributes->set('organization', $organization);
        app()->instance(Organization::class, $organization);
        view()->share('currentOrganization', $organization);

        return $next($request);
    }
}