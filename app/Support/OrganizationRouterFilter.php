<?php

namespace App\Support;

use App\Models\NetworkDevice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

final class OrganizationRouterFilter
{
    /**
     * @return array{0: Collection<int, NetworkDevice>, 1: ?int}
     */
    public static function resolve(Request $request, Organization $organization, string $key = 'router'): array
    {
        $routers = $organization->networkDevices()
            ->with('location')
            ->orderBy('name')
            ->get();

        $requested = ListFilters::id($request, $key);
        $selected = $requested !== null && $routers->contains('id', $requested)
            ? $requested
            : null;

        return [$routers, $selected];
    }
}
