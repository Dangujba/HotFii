@extends('layouts.platform')
@section('title', $organization->name)
@section('heading', $organization->name)
@section('subheading', $organization->slug . ' · ' . ucfirst($organization->mode->value) . ' · joined ' . $organization->created_at->format('j M Y'))
@section('actions')<a href="{{ route('platform.organizations.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All organizations</a>@endsection
@section('content')

@if($organization->trashed())
    <div class="alert alert-danger"><i class="bi bi-trash3 me-2"></i><strong>This organization is deleted.</strong> It was soft-deleted {{ $organization->deleted_at->diffForHumans() }} and its data is retained but hidden from the operator app.</div>
@endif

<div class="row g-3 mb-4">
    @foreach([
        ['Status', str_replace('_', ' ', ucfirst($organization->status->value))],
        ['Billing plan', str_replace('_', ' ', ucfirst($organization->billing_plan->value))],
        ['Lifetime volume', \App\Support\Naira::from($lifetime['volume'])],
        ['Fees earned', \App\Support\Naira::from($lifetime['fees'])],
        ['Fees collected', \App\Support\Naira::from($lifetime['collected'])],
        ['Fees this month', \App\Support\Naira::from($lifetime['this_month_fees'])],
    ] as [$label, $value])
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card metric-card h-100"><div class="card-body">
                <div class="text-secondary small">{{ $label }}</div>
                <div class="fs-5 fw-bold">{{ $value }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        {{-- Everything about collecting real money. Displayed, never edited: the
             subaccount code is what Paystack settles against. --}}
        <div class="card metric-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Live payments</h2>
                <span class="badge text-bg-{{ $organization->canCollectLivePayments() ? 'success' : 'secondary' }}">{{ $organization->canCollectLivePayments() ? 'Collecting' : 'Not collecting' }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5">Settlement subaccount</dt>
                    <dd class="col-sm-7">
                        @if(filled($organization->paystack_subaccount_code))
                            <code>{{ $organization->paystack_subaccount_code }}</code>
                            <button type="button" class="btn btn-sm btn-link p-0 ms-1 text-decoration-none" data-copy-text="{{ $organization->paystack_subaccount_code }}"><i class="bi bi-clipboard"></i></button>
                        @else
                            <span class="text-secondary">none</span>
                        @endif
                    </dd>
                    <dt class="col-sm-5">Enabled at</dt>
                    <dd class="col-sm-7">{{ $organization->live_payments_enabled_at?->format('j M Y, H:i') ?? '—' }}</dd>
                    @if($organization->paymentProfile)
                        <dt class="col-sm-5">Profile status</dt>
                        <dd class="col-sm-7">
                            <span class="badge text-bg-{{ $organization->paymentProfile->status === 'approved' ? 'success' : ($organization->paymentProfile->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($organization->paymentProfile->status) }}</span>
                            @if($organization->paymentProfile->wasAutoApproved())<span class="text-secondary">automatically</span>@endif
                        </dd>
                        <dt class="col-sm-5">Business</dt>
                        <dd class="col-sm-7">{{ $organization->paymentProfile->business_name }}</dd>
                        <dt class="col-sm-5">Contact</dt>
                        <dd class="col-sm-7">{{ $organization->paymentProfile->contact_name }} · {{ $organization->paymentProfile->contact_phone }}</dd>
                        <dt class="col-sm-5">Bank</dt>
                        <dd class="col-sm-7">{{ $organization->paymentProfile->bank_name }} · {{ $organization->paymentProfile->accountNumberHint() ?? '—' }}</dd>
                        <dt class="col-sm-5">Account name</dt>
                        <dd class="col-sm-7">
                            {{ $organization->paymentProfile->account_name }}
                            @if($organization->paymentProfile->resolved_account_name && $organization->paymentProfile->resolved_account_name !== $organization->paymentProfile->account_name)
                                <div class="text-danger">Bank says: {{ $organization->paymentProfile->resolved_account_name }}</div>
                            @endif
                        </dd>
                        <dt class="col-sm-5">Identity</dt>
                        <dd class="col-sm-7">{{ strtoupper($organization->paymentProfile->identity_type ?? '—') }} · {{ $organization->paymentProfile->identityNumberHint() ?? '—' }}</dd>
                        <dt class="col-sm-5">Reviewed by</dt>
                        <dd class="col-sm-7">{{ $organization->paymentProfile->reviewer?->name ?? ($organization->paymentProfile->wasAutoApproved() ? 'System' : '—') }}@if($organization->paymentProfile->reviewed_at) · {{ $organization->paymentProfile->reviewed_at->format('j M Y') }}@endif</dd>
                        @if($organization->paymentProfile->review_notes)
                            <dt class="col-sm-5">Review note</dt>
                            <dd class="col-sm-7">{{ $organization->paymentProfile->review_notes }}</dd>
                        @endif
                    @else
                        <dt class="col-sm-5">Payment profile</dt>
                        <dd class="col-sm-7 text-secondary">Never submitted.</dd>
                    @endif
                </dl>
                @if($organization->paymentProfile?->status === 'submitted')
                    <a href="{{ route('platform.reviews.index') }}" class="btn btn-sm btn-hotfii mt-3"><i class="bi bi-person-check me-1"></i>Review this profile</a>
                @endif
            </div>
        </div>

        <div class="card metric-card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Trial &amp; subscription</h2></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5">Trial started</dt><dd class="col-sm-7">{{ $organization->trial_started_at?->format('j M Y') ?? '—' }}</dd>
                    <dt class="col-sm-5">Trial ends</dt><dd class="col-sm-7">{{ $organization->trial_ends_at?->format('j M Y') ?? '—' }}@if($organization->trial_ends_at) <span class="text-secondary">({{ $organization->trial_ends_at->diffForHumans() }})</span>@endif</dd>
                    <dt class="col-sm-5">Trial sales used</dt><dd class="col-sm-7">{{ \App\Support\Naira::from($organization->trial_sales_kobo) }} of {{ \App\Support\Naira::from((int) config('hotfii.commerce.trial_sales_cap_kobo')) }}</dd>
                    <dt class="col-sm-5">Sales this month</dt><dd class="col-sm-7">{{ \App\Support\Naira::from($organization->monthly_sales_kobo) }}</dd>
                    @if($subscription)
                        <dt class="col-sm-5">Subscription</dt><dd class="col-sm-7">{{ str_replace('_', ' ', ucfirst($subscription->plan_code)) }} · {{ ucfirst($subscription->status) }} · {{ \App\Support\Naira::from($subscription->amount_kobo) }}</dd>
                        <dt class="col-sm-5">Period ends</dt><dd class="col-sm-7">{{ $subscription->current_period_ends_at?->format('j M Y') ?? '—' }}</dd>
                        @if($subscription->grace_ends_at)<dt class="col-sm-5">Grace ends</dt><dd class="col-sm-7">{{ $subscription->grace_ends_at->format('j M Y') }}</dd>@endif
                    @else
                        <dt class="col-sm-5">Subscription</dt><dd class="col-sm-7 text-secondary">No subscription row.</dd>
                    @endif
                </dl>
                <p class="small text-secondary mb-0 mt-3"><i class="bi bi-info-circle me-1"></i>Plans and trial dates are not editable here. They change what this customer owes, so they stay a server-side change.</p>
            </div>
        </div>

        <div class="card metric-card">
            <div class="card-header"><h2 class="h5 mb-0">Identity</h2></div>
            <div class="card-body"><dl class="row mb-0 small">
                <dt class="col-sm-5">Public UUID</dt>
                <dd class="col-sm-7"><code>{{ $organization->uuid }}</code><button type="button" class="btn btn-sm btn-link p-0 ms-1 text-decoration-none" data-copy-text="{{ $organization->uuid }}"><i class="bi bi-clipboard"></i></button></dd>
                <dt class="col-sm-5">Mode</dt><dd class="col-sm-7">{{ ucfirst($organization->mode->value) }}{{ $organization->sellsAccess() ? ' (sells access)' : '' }}</dd>
                <dt class="col-sm-5">Currency</dt><dd class="col-sm-7">{{ $organization->currency }}</dd>
                <dt class="col-sm-5">Timezone</dt><dd class="col-sm-7">{{ $organization->timezone }}</dd>
                <dt class="col-sm-5">Registered</dt><dd class="col-sm-7">{{ $organization->created_at->format('j M Y, H:i') }}</dd>
            </dl></div>
        </div>
    </div>

    <div class="col-xl-5">
        {{-- The two write actions available on a tenant. Both demand a written
             reason and both land in the audit log. A deleted account gets
             neither: there is nothing left to support or suspend. --}}
        @if(! $organization->trashed())
        <div class="card metric-card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Support actions</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('platform.impersonate.start', $organization) }}">@csrf
                    <label class="form-label" for="impersonate-reason">Open their dashboard as support</label>
                    <div class="input-group">
                        <input id="impersonate-reason" class="form-control" name="reason" minlength="10" placeholder="Written support reason" required>
                        <button class="btn btn-outline-primary"
                            data-confirm-title="Enter support mode for {{ $organization->name }}?"
                            data-confirm="You will see their customers, sessions and finances as they do. The reason you typed is written to the audit log against your account."
                            data-confirm-icon="warning"
                            data-confirm-button="Enter support mode">Open</button>
                    </div>
                </form>
                <hr class="my-4">
                <form method="POST" action="{{ route('platform.organizations.status', $organization) }}">@csrf @method('PATCH')
                    @if($organization->status === \App\Domain\Enums\OrganizationStatus::Suspended)
                        <input type="hidden" name="action" value="reactivate">
                        <label class="form-label" for="status-reason">Reactivate this organization</label>
                        <div class="input-group">
                            <input id="status-reason" class="form-control" name="reason" minlength="10" placeholder="Why it is being reactivated" required>
                            <button class="btn btn-success"
                                data-confirm-title="Reactivate {{ $organization->name }}?"
                                data-confirm="They can sell again straight away. HotFii puts them back to {{ $organization->paymentProfileActivated() ? 'Live' : ($organization->trial_started_at ? 'Trial' : 'Sandbox') }}."
                                data-confirm-icon="warning"
                                data-confirm-button="Reactivate">Reactivate</button>
                        </div>
                    @else
                        <input type="hidden" name="action" value="suspend">
                        <label class="form-label" for="status-reason">Suspend this organization</label>
                        <div class="input-group">
                            <input id="status-reason" class="form-control" name="reason" minlength="10" placeholder="Why it is being suspended" required>
                            <button class="btn btn-outline-danger"
                                data-confirm-title="Suspend {{ $organization->name }}?"
                                data-confirm="Their portal stops taking payments immediately and their staff lose access to selling. The reason you typed is written to the audit log against your account."
                                data-confirm-icon="danger"
                                data-confirm-button="Suspend">Suspend</button>
                        </div>
                    @endif
                    <div class="form-text">At least 10 characters. This is the record of why, so write it for whoever reads it in six months.</div>
                </form>
            </div>
        </div>
        @endif

        <div class="card metric-card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">What they have built</h2></div>
            <div class="list-group list-group-flush">@foreach($counts as $label => $count)
                <div class="list-group-item d-flex justify-content-between"><span>{{ $label }}</span><strong>{{ number_format($count) }}</strong></div>
            @endforeach</div>
        </div>

        <div class="card metric-card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Members</h2></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Name</th><th>Role</th><th>Joined</th></tr></thead>
                <tbody>@forelse($organization->users as $member)
                    <tr>
                        <td>{{ $member->name }}<div class="small text-secondary">{{ $member->email }}@if(! $member->hasVerifiedEmail()) · <span class="text-warning">unverified</span>@endif</div></td>
                        <td class="small">{{ ucfirst($member->pivot->role) }}</td>
                        {{-- The pivot has no date cast, so this is a raw string. --}}
                        <td class="small text-secondary">{{ $member->pivot->joined_at ? \Carbon\Carbon::parse($member->pivot->joined_at)->format('j M Y') : '—' }}</td>
                    </tr>
                @empty<tr><td colspan="3" class="text-center py-4 text-secondary">No members.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>

        <div class="card metric-card">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Audit history</h2><a class="small" href="{{ route('platform.audit.index', ['organization' => $organization->id]) }}">Full log</a></div>
            <div class="list-group list-group-flush">@forelse($audits as $log)
                <div class="list-group-item">
                    <strong>{{ str_replace('.', ' · ', $log->action) }}</strong>
                    <div class="small text-secondary">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</div>
                    @if($log->reason)<div class="small mt-1">{{ $log->reason }}</div>@endif
                </div>
            @empty<div class="p-5 text-center text-secondary">No audited changes yet.</div>@endforelse</div>
        </div>
    </div>
</div>

<div class="card metric-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Recent payments</h2><a class="small" href="{{ route('platform.transactions.index', ['organization' => $organization->id]) }}">All payments</a></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Plan</th><th>Channel</th><th class="text-end">Amount</th><th class="text-end">Fee</th><th>Status</th><th>Paid</th></tr></thead>
        <tbody>@forelse($transactions as $transaction)
            <tr>
                <td><code>{{ $transaction->reference }}</code></td>
                <td class="small">{{ $transaction->accessPlan?->name ?? '—' }}</td>
                <td class="small">{{ ucfirst($transaction->channel) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->gross_amount_kobo) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->platform_fee_kobo) }}</td>
                <td><span class="badge text-bg-{{ $transaction->status->value === 'successful' ? 'success' : ($transaction->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($transaction->status->value) }}</span></td>
                <td class="small text-secondary">{{ $transaction->paid_at?->format('j M Y, H:i') ?? '—' }}</td>
            </tr>
        @empty<tr><td colspan="7" class="text-center py-5 text-secondary">No payments yet.</td></tr>@endforelse</tbody>
    </table></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Invoices</h2><a class="small" href="{{ route('platform.billing.index') }}">Billing</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Number</th><th>Period</th><th class="text-end">Total</th><th>Status</th><th>Due</th></tr></thead>
                <tbody>@forelse($invoices as $invoice)
                    <tr>
                        <td><code class="small">{{ $invoice->number }}</code></td>
                        <td class="small">{{ $invoice->billing_period->format('M Y') }}</td>
                        <td class="text-end">{{ \App\Support\Naira::from($invoice->total_kobo) }}</td>
                        <td><span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'open' ? 'warning' : 'secondary') }}">{{ ucfirst($invoice->status) }}</span></td>
                        <td class="small text-secondary">{{ $invoice->due_at?->format('j M Y') ?? '—' }}</td>
                    </tr>
                @empty<tr><td colspan="5" class="text-center py-4 text-secondary">No invoices generated yet.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Fee ledger</h2></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Period</th><th>Source</th><th class="text-end">Sales</th><th class="text-end">Fee</th><th>Status</th></tr></thead>
                <tbody>@forelse($entries as $entry)
                    <tr>
                        <td class="small">{{ $entry->billing_period->format('M Y') }}</td>
                        <td class="small">{{ str_replace('_', ' ', ucfirst($entry->source_type)) }}</td>
                        <td class="text-end">{{ \App\Support\Naira::from($entry->billable_sales_kobo) }}</td>
                        <td class="text-end">{{ \App\Support\Naira::from($entry->fee_amount_kobo) }}</td>
                        <td><span class="badge text-bg-{{ $entry->status === 'collected' ? 'success' : 'secondary' }}">{{ ucfirst($entry->status) }}</span></td>
                    </tr>
                @empty<tr><td colspan="5" class="text-center py-4 text-secondary">No platform fees recorded yet.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>
    </div>
</div>
@endsection
