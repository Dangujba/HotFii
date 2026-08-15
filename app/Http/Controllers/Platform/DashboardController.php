<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PaymentWebhook;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        try {
            Redis::connection()->ping();
            $redis = 'online';
        } catch (Throwable) {
            $redis = 'offline';
        }

        return view('platform.index', [
            'stats' => [
                'organizations' => Organization::count(),
                'live_organizations' => Organization::where('status', 'live')->count(),
                'monthly_volume' => Transaction::where('status', 'successful')->where('paid_at', '>=', now()->startOfMonth())->sum('gross_amount_kobo'),
                'pending_reviews' => Organization::where('status', 'payment_review')->count(),
            ],
            'paymentRequests' => Organization::where('status', 'payment_review')->with('paymentProfile')->oldest()->get(),
            'recentOrganizations' => Organization::latest()->limit(10)->get(),
            'health' => [
                'redis' => $redis,
                'queued_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'pending_webhooks' => PaymentWebhook::whereNull('processed_at')->count(),
                'reverb' => config('broadcasting.default') === 'reverb' ? 'configured' : 'disabled',
            ],
        ]);
    }
}