<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PaymentWebhook;
use App\Models\Transaction;
use App\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        try {
            Redis::connection()->ping();
            $redis = 'online';
        } catch (Throwable) {
            $redis = 'offline';
        }

        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', ListFilters::enumValues(OrganizationStatus::class)),
            'mode' => ListFilters::choice($request, 'mode', ListFilters::enumValues(OrganizationMode::class)),
        ];

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
            'organizations' => Organization::query()
                ->when($filters['search'], fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['mode'], fn ($query, $mode) => $query->where('mode', $mode))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'statuses' => OrganizationStatus::cases(),
            'modes' => OrganizationMode::cases(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
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