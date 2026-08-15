<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($request->user()->is_platform_admin && $request->session()->has('impersonated_organization_id')) {
            return $next($request);
        }

        $organization = $request->attributes->get('organization');
        $role = $request->user()->roleFor($organization);
        abort_unless(in_array($role, $roles, true), 403, 'Your organization role cannot perform this action.');

        return $next($request);
    }
}