<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use App\Support\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenants, as the platform owner sees them.
 *
 * Read-only apart from status(): suspending an account is the one lever here
 * that changes what a customer can do, so it is the one write. Billing plans,
 * trials and payment profiles are deliberately not editable from this page —
 * those change what somebody owes, and a mis-click would be invisible.
 */
class OrganizationController extends Controller
{
    private const VOLUME_WINDOW_DAYS = 30;

    public function index(Request $request): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', ListFilters::enumValues(OrganizationStatus::class)),
            'mode' => ListFilters::choice($request, 'mode', ListFilters::enumValues(OrganizationMode::class)),
            'plan' => ListFilters::choice($request, 'plan', ListFilters::enumValues(BillingPlan::class)),
            'collecting' => ListFilters::choice($request, 'collecting', ['yes', 'no']),
            // Deleted accounts are hidden by default so the list agrees with the
            // overview counts, but they stay reachable: "where did that
            // organization go" is a support question with no other answer.
            'deleted' => ListFilters::choice($request, 'deleted', ['yes']),
        ];

        $since = CarbonImmutable::today()->subDays(self::VOLUME_WINDOW_DAYS - 1)->startOfDay();

        return view('platform.organizations.index', [
            'organizations' => Organization::query()
                ->withCount('users')
                ->withSum(
                    ['transactions as volume_kobo' => fn ($query) => $query->where('status', 'successful')->where('paid_at', '>=', $since)],
                    'gross_amount_kobo',
                )
                ->when($filters['search'], fn ($query, $term) => $query->where(
                    fn ($group) => $group->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%"),
                ))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['mode'], fn ($query, $mode) => $query->where('mode', $mode))
                ->when($filters['plan'], fn ($query, $plan) => $query->where('billing_plan', $plan))
                ->when($filters['collecting'] === 'yes', fn ($query) => $query->whereNotNull('live_payments_enabled_at')->whereNotNull('paystack_subaccount_code'))
                ->when($filters['collecting'] === 'no', fn ($query) => $query->where(
                    fn ($group) => $group->whereNull('live_payments_enabled_at')->orWhereNull('paystack_subaccount_code'),
                ))
                ->when($filters['deleted'] === 'yes', fn ($query) => $query->onlyTrashed())
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'statuses' => OrganizationStatus::cases(),
            'modes' => OrganizationMode::cases(),
            'plans' => BillingPlan::cases(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
            'windowDays' => self::VOLUME_WINDOW_DAYS,
        ]);
    }

    public function show(Organization $organization): View
    {
        $organization->load(['paymentProfile.reviewer', 'users']);

        $period = CarbonImmutable::now()->startOfMonth()->toDateString();

        return view('platform.organizations.show', [
            'organization' => $organization,
            'counts' => [
                'Locations' => $organization->locations()->count(),
                'Network devices' => $organization->networkDevices()->count(),
                'Access plans' => $organization->accessPlans()->count(),
                'Customers' => $organization->customers()->count(),
                'Vouchers' => $organization->vouchers()->count(),
                'Sessions' => $organization->sessions()->count(),
            ],
            'lifetime' => [
                'volume' => (int) $organization->transactions()->where('status', 'successful')->sum('gross_amount_kobo'),
                'fees' => (int) FeeLedgerEntry::where('organization_id', $organization->id)->sum('fee_amount_kobo'),
                'collected' => (int) FeeLedgerEntry::where('organization_id', $organization->id)->where('status', 'collected')->sum('fee_amount_kobo'),
                'this_month_fees' => (int) FeeLedgerEntry::where('organization_id', $organization->id)->whereDate('billing_period', $period)->sum('fee_amount_kobo'),
            ],
            'subscription' => $organization->subscriptions()->latest()->first(),
            'transactions' => $organization->transactions()->with('accessPlan')->latest()->limit(10)->get(),
            'invoices' => Invoice::where('organization_id', $organization->id)->latest()->limit(10)->get(),
            'entries' => FeeLedgerEntry::where('organization_id', $organization->id)->latest()->limit(10)->get(),
            'audits' => AuditLog::where('organization_id', $organization->id)->with('user')->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Suspend or reactivate an organization.
     *
     * A reason is required and written to the audit log, because suspension
     * stops a paying business from selling and somebody will ask later why.
     */
    public function status(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:suspend,reactivate'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $suspended = $organization->status === OrganizationStatus::Suspended;

        // Refuse the no-op rather than writing an audit row that records nothing.
        abort_if($data['action'] === 'suspend' && $suspended, 422, 'That organization is already suspended.');
        abort_if($data['action'] === 'reactivate' && ! $suspended, 422, 'That organization is not suspended.');

        $before = $organization->status;
        $after = $data['action'] === 'suspend'
            ? OrganizationStatus::Suspended
            : $this->reactivatedStatus($organization);

        $organization->update(['status' => $after]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => $data['action'] === 'suspend' ? 'organization.suspended' : 'organization.reactivated',
            'subject_type' => Organization::class,
            'subject_id' => $organization->id,
            'ip_address' => $request->ip(),
            'reason' => $data['reason'],
            'before' => ['status' => $before->value],
            'after' => ['status' => $after->value],
        ]);

        return back()->with('success', $data['action'] === 'suspend'
            ? $organization->name.' is suspended. Their portal stops taking payments immediately.'
            : $organization->name.' is active again, as '.str_replace('_', ' ', $after->value).'.');
    }

    /**
     * Where a reactivated organization lands.
     *
     * Suspension does not record what the status was before it, so this is
     * derived from the account itself rather than guessed: an approved
     * settlement account means Live, a started trial means Trial, and anything
     * untouched goes back to Sandbox.
     */
    private function reactivatedStatus(Organization $organization): OrganizationStatus
    {
        if ($organization->live_payments_enabled_at !== null && filled($organization->paystack_subaccount_code)) {
            return OrganizationStatus::Live;
        }

        return $organization->trial_started_at !== null
            ? OrganizationStatus::Trial
            : OrganizationStatus::Sandbox;
    }
}
