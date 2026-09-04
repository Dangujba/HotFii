<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Support\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The audit trail across every tenant.
 *
 * Until now these rows were only visible per-organization, on that
 * organization's own settings page, which meant nobody could answer "what did
 * my support staff do today" without SQL. Read-only: an audit log that can be
 * edited from the application it audits is not one.
 */
class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        // The action list is built from what has actually been recorded rather
        // than a hardcoded whitelist, so a new audited action appears in the
        // filter the first time it is written.
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action')->all();

        $filters = [
            'action' => ListFilters::choice($request, 'action', $actions),
            'organization' => ListFilters::id($request, 'organization'),
            'actor' => ListFilters::text($request, 'actor'),
            'from' => ListFilters::date($request, 'from'),
            'to' => ListFilters::date($request, 'to'),
        ];

        return view('platform.audit', [
            'logs' => AuditLog::query()
                ->with(['user', 'organization'])
                ->when($filters['action'], fn ($query, $action) => $query->where('action', $action))
                ->when($filters['organization'], fn ($query, $id) => $query->where('organization_id', $id))
                ->when($filters['actor'], fn ($query, $term) => $query->whereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")))
                ->when($filters['from'], fn ($query, $date) => $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
                ->when($filters['to'], fn ($query, $date) => $query->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'actions' => $actions,
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }
}
