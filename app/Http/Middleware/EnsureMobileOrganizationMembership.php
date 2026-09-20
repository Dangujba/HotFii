<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileOrganizationMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        abort_unless(
            $organization instanceof Organization
                && $request->user()->organizations()->whereKey($organization->id)->exists(),
            403,
            'You do not belong to this organization.',
        );

        return $next($request);
    }
}
