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
                // Every organization is Live from registration, so the useful
                // count is how many can actually collect money.
                'live_organizations' => Organization::whereNotNull('live_payments_enabled_at')
                    ->whereNotNull('paystack_subaccount_code')
                    ->count(),
                'monthly_volume' => Transaction::where('status', 'successful')->where('paid_at', '>=', now()->startOfMonth())->sum('gross_amount_kobo'),
                'pending_reviews' => Organization::whereHas('paymentProfile', fn ($query) => $query->where('status', 'submitted'))->count(),
            ],
            // Keyed off the profile, not the organization status: organizations
            // stay Live while a profile waits, so status cannot signal this.
            'paymentRequests' => Organization::whereHas('paymentProfile', fn ($query) => $query->where('status', 'submitted'))
                ->with('paymentProfile')
                ->oldest()
                ->get(),
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