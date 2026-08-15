<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Jobs\DisconnectHotspotSession;
use App\Models\HotspotSession;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.sessions', [
            'sessions' => $organization->sessions()
                ->with('networkDevice', 'customer', 'accessPlan')
                ->latest('started_at')
                ->paginate(30),
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