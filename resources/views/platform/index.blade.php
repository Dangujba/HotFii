@extends('layouts.platform')
@section('title', 'Overview')
@section('heading', 'Platform Overview')
@section('subheading', 'Every tenant, every naira, across the whole deployment')
@section('actions')<a href="{{ route('platform.organizations.index') }}" class="btn btn-hotfii"><i class="bi bi-buildings me-1"></i>All organizations</a>@endsection
@section('content')

<div class="row g-3 mb-4">
    @foreach([
        ['Organizations', number_format($stats['organizations']), 'buildings', 'primary', route('platform.organizations.index')],
        ['Collecting payments', number_format($stats['collecting']), 'broadcast-pin', 'success', route('platform.organizations.index', ['collecting' => 'yes'])],
        ['Volume this month', \App\Support\Naira::from($stats['monthly_volume']), 'cash-stack', 'info', route('platform.transactions.index')],
        ['Fees this month', \App\Support\Naira::from($stats['monthly_fees']), 'percent', 'primary', route('platform.billing.index')],
        ['Invoices outstanding', \App\Support\Naira::from($stats['open_invoices']), 'receipt', 'warning', route('platform.billing.index', ['invoice_status' => 'open'])],
        ['Payment reviews', number_format($stats['pending_reviews']), 'person-check', $stats['pending_reviews'] ? 'danger' : 'secondary', route('platform.reviews.index')],
    ] as [$label, $value, $icon, $tone, $href])
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <a href="{{ $href }}" class="text-decoration-none text-reset">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">{{ $label }}</div>
                        <div class="fs-4 fw-bold">{{ $value }}</div><i class="bi bi-{{ $icon }} text-{{ $tone }}"></i>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

{{-- Money over time, then how the tenant base is distributed. --}}
<div class="row g-4 mb-4">
    <div class="col-xxl-8">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="hf-chart-eyebrow">Last 14 days</span>
                    <h2 class="h5 mb-0">Gross volume processed</h2>
                </div>
                <div class="text-end">
                    <span class="hf-chart-eyebrow">Total</span>
                    <div class="fs-5 fw-bold">₦{{ number_format($volume['total'], 0) }}</div>
                </div>
            </div>
            <div class="card-body pt-2 px-3 pb-3">
                @if($volume['total'] > 0)
                    <div id="hf-volume-chart" class="hf-chart" role="img"
                         aria-label="Gross volume processed per day across all organizations for the last 14 days, in naira."
                         data-labels='@json($volume['labels'])'
                         data-values='@json($volume['values'])'></div>
                    {{-- The table view. Every plotted value is readable without a tooltip. --}}
                    <table class="visually-hidden">
                        <caption>Gross volume by day</caption>
                        <thead><tr><th scope="col">Day</th><th scope="col">Volume</th></tr></thead>
                        <tbody>
                            @foreach($volume['labels'] as $index => $label)
                                <tr><th scope="row">{{ $label }}</th><td>₦{{ number_format($volume['values'][$index], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-graph-up-arrow fs-1 d-block mb-2 opacity-50"></i>
                        No payments collected anywhere in the last 14 days.
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4">
                <span class="hf-chart-eyebrow">Right now</span>
                <h2 class="h5 mb-0">Accounts by status</h2>
            </div>
            <div class="card-body pt-2 px-3 pb-3">
                @if($statusMix['total'] > 0)
                    <div id="hf-status-chart" class="hf-chart-sm" role="img"
                         aria-label="Organizations in each account status."
                         data-labels='@json($statusMix['labels'])'
                         data-values='@json($statusMix['values'])'></div>
                    {{-- Also the way to reach a filtered list, so the counts are
                         never only readable off the bars. --}}
                    <div class="hf-legend mt-3">
                        @foreach($statusMix['rows'] as $row)
                            <div class="hf-legend-row @if($row['count'] === 0) is-empty @endif">
                                <span class="flex-grow-1"><a class="text-decoration-none text-reset" href="{{ route('platform.organizations.index', ['status' => $row['value']]) }}">{{ $row['label'] }}</a></span>
                                <span class="hf-legend-count fw-semibold">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-buildings fs-1 d-block mb-2 opacity-50"></i>
                        No organizations have registered yet.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- What the platform earned, and what it actually received. --}}
<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="hf-chart-eyebrow">Last 6 months</span>
                    <h2 class="h5 mb-0">Platform fees earned and collected</h2>
                </div>
                <a href="{{ route('platform.billing.index') }}" class="small">Billing detail</a>
            </div>
            <div class="card-body pt-2 px-3 pb-3">
                @if($fees['accrued_total'] > 0)
                    <div id="hf-fees-chart" class="hf-chart-sm" role="img"
                         aria-label="Platform fees earned against fees collected, for each of the last six months, in naira."
                         data-labels='@json($fees['labels'])'
                         data-accrued='@json($fees['accrued'])'
                         data-collected='@json($fees['collected'])'></div>
                    <table class="visually-hidden">
                        <caption>Fees earned and collected by month</caption>
                        <thead><tr><th scope="col">Month</th><th scope="col">Earned</th><th scope="col">Collected</th></tr></thead>
                        <tbody>
                            @foreach($fees['labels'] as $index => $label)
                                <tr><th scope="row">{{ $label }}</th><td>₦{{ number_format($fees['accrued'][$index], 2) }}</td><td>₦{{ number_format($fees['collected'][$index], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small text-secondary mb-0 px-2">
                        ₦{{ number_format($fees['accrued_total'], 0) }} earned, ₦{{ number_format($fees['collected_total'], 0) }} collected at the gateway. The gap is what the monthly invoice bills.
                    </p>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-percent fs-1 d-block mb-2 opacity-50"></i>
                        No platform fees recorded yet.
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between"><h2 class="h5 mb-0">Newest organizations</h2><a href="{{ route('platform.organizations.index') }}" class="small">View all</a></div>
            <div class="list-group list-group-flush">@forelse($organizations as $organization)
                <a href="{{ route('platform.organizations.show', $organization) }}" class="list-group-item list-group-item-action px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $organization->name }}</strong>
                        <div class="small text-secondary">{{ ucfirst($organization->mode->value) }} · {{ $organization->users_count }} {{ $organization->users_count === 1 ? 'member' : 'members' }} · {{ $organization->created_at->diffForHumans() }}</div>
                    </div>
                    <span class="badge text-bg-{{ $organization->status === \App\Domain\Enums\OrganizationStatus::Suspended ? 'danger' : ($organization->paymentProfileActivated() ? 'success' : 'secondary') }}">{{ str_replace('_', ' ', ucfirst($organization->status->value)) }}</span>
                </a>
            @empty<div class="p-5 text-center text-secondary">Organizations will appear here as they register.</div>@endforelse</div>
        </div>
    </div>
</div>

<div class="card metric-card">
    <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Latest payments</h2><a href="{{ route('platform.transactions.index') }}" class="small">All transactions</a></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Organization</th><th class="text-end">Amount</th><th class="text-end">Fee</th><th>Status</th><th>When</th></tr></thead>
        <tbody>@forelse($transactions as $transaction)
            <tr>
                <td><code>{{ $transaction->reference }}</code></td>
                <td>@if($transaction->organization)<a class="text-decoration-none" href="{{ route('platform.organizations.show', $transaction->organization) }}">{{ $transaction->organization->name }}</a>@else<span class="text-secondary">—</span>@endif</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->gross_amount_kobo) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->platform_fee_kobo) }}</td>
                <td><span class="badge text-bg-{{ $transaction->status->value === 'successful' ? 'success' : ($transaction->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($transaction->status->value) }}</span></td>
                <td>{{ $transaction->created_at->diffForHumans() }}</td>
            </tr>
        @empty<tr><td colspan="6" class="text-center py-5 text-secondary">No transactions yet.</td></tr>@endforelse</tbody>
    </table></div></div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/platform.js')
@endpush
