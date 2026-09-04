<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'state' => ListFilters::choice($request, 'state', ['unread', 'read']),
        ];

        return view('operator.notifications', [
            // notifications.data is a text column, so a plain LIKE reaches the
            // title and message without any JSON operators.
            'notifications' => $request->user()->notifications()
                ->when($filters['search'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('data', 'like', "%{$term}%")
                    ->orWhere('type', 'like', "%{$term}%")))
                ->when($filters['state'] === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->when($filters['state'] === 'read', fn ($query) => $query->whereNotNull('read_at'))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function read(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Notifications marked as read.');
    }
}