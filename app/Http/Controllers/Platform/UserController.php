<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who has an account on the deployment.
 *
 * Read-only, deliberately and completely. There is no grant-admin button, no
 * password reset and no force-verify: the platform-admin flag is the most
 * powerful thing in this application — it can impersonate every tenant — so it
 * is granted only by someone with shell access, through
 * `php artisan hotfii:create-admin`. A web button would make a stolen admin
 * session enough to mint more admins.
 */
class UserController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'verified' => ListFilters::choice($request, 'verified', ['yes', 'no']),
            'admins' => ListFilters::choice($request, 'admins', ['yes']),
        ];

        return view('platform.users', [
            'users' => User::query()
                // uuid included because that is the organization route key.
                ->with('organizations:id,uuid,name')
                ->when($filters['search'], fn ($query, $term) => $query->where(
                    fn ($group) => $group->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"),
                ))
                ->when($filters['verified'] === 'yes', fn ($query) => $query->whereNotNull('email_verified_at'))
                ->when($filters['verified'] === 'no', fn ($query) => $query->whereNull('email_verified_at'))
                ->when($filters['admins'] === 'yes', fn ($query) => $query->where('is_platform_admin', true))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'stats' => [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'admins' => User::where('is_platform_admin', true)->count(),
                'orphaned' => User::doesntHave('organizations')->where('is_platform_admin', false)->count(),
            ],
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }
}
