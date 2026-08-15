<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('operator.notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(30),
        ]);
    }

    public function read(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Notifications marked as read.');
    }
}