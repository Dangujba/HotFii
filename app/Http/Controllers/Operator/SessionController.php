<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Jobs\DisconnectHotspotSession;
use App\Models\HotspotSession;
use App\Models\Organization;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    /** Values the RADIUS accounting flow writes to hotspot_sessions.status. */
    private const STATUSES = ['pending', 'active', 'disconnect_pending', 'stopped', 'expired'];

    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', self::STATUSES),
            'device' => ListFilters::id($request, 'device'),
        ];

        return view('operator.sessions', [
            'sessions' => $organization->sessions()
                ->with('networkDevice', 'customer', 'accessPlan')
                ->when($filters['search'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('radius_username', 'like', "%{$term}%")
                    ->orWhere('mac_address', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"))))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['device'], fn ($query, $device) => $query->where('network_device_id', $device))
                ->latest('started_at')
                ->paginate(30)
                ->withQueryString(),
            'devices' => $organization->networkDevices()->orderBy('name')->get(['id', 'name']),
            'statuses' => self::STATUSES,
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function disconnect(Request $request, HotspotSession $session): RedirectResponse
    {
        abort_unless($session->organization_id === $request->attributes->get('organization')->id, 404);
        abort_unless(in_array($session->status, ['active', 'disconnect_pending'], true), 422, 'This session is not active.');

        $session->update(['status' => 'disconnect_pending']);
        DisconnectHotspotSession::dispatch($session);

        return back()->with('success', 'Disconnect request queued.');
    }
}