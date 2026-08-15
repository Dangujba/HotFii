<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Organization $organization): View
    {
        return view('dashboard.index',['stats'=>[
            'revenue_today'=>$organization->transactions()->whereDate('paid_at',today())->where('status','successful')->sum('gross_amount_kobo'),
            'active_sessions'=>$organization->sessions()->where('status','active')->count(),
            'online_routers'=>$organization->networkDevices()->where('status','online')->count(),
            'available_vouchers'=>$organization->vouchers()->whereIn('status',['generated','printed','assigned','sold'])->count(),
        ],'devices'=>$organization->networkDevices()->with('location')->latest()->limit(6)->get(),'transactions'=>$organization->transactions()->latest()->limit(6)->get()]);
    }
}