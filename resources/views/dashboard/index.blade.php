@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.strtok(auth()->user()->name, ' '))
@section('subheading', 'A live view of '.$currentOrganization->name)
@section('actions')<a href="{{ route('network.devices.index') }}" class="btn btn-hotfii"><i class="bi bi-plus-lg me-1"></i>Add network device</a>@endsection
@section('content')
<livewire:dashboard-pulse :organization-uuid="$currentOrganization->uuid" />

@if($currentOrganization->billing_suspended_at)
    {{-- Being held over an unpaid bill used to be silent: sales simply started
         failing with no explanation anywhere in the product, and there was no
         way to pay. Both halves of that are fixed, so say so plainly. --}}
    <div class="alert alert-danger border-0 shadow-sm">
        <div class="d-flex align-items-center"><i class="bi bi-exclamation-octagon fs-3 me-3"></i>
            <div class="flex-grow-1">
                <strong>{{ $currentOrganization->status === \App\Domain\Enums\OrganizationStatus::Suspended ? 'Your account is suspended over an unpaid invoice.' : 'You have an overdue invoice.' }}</strong>
                <div>
                    @if($currentOrganization->status === \App\Domain\Enums\OrganizationStatus::Suspended)
                        Counter sales, voucher activation and portal payments are all blocked until it is settled. Customers already online are unaffected.
                    @else
                        Counter sales are blocked. Your account is suspended once the grace period ends. Settling the invoice restores it within the hour.
                    @endif
                </div>
            </div>
            <a href="{{ route('finance.index') }}" class="btn btn-light ms-3 flex-shrink-0">Pay invoice</a>
        </div>
    </div>
@endif

@if($currentOrganization->sellsAccess() && ! $currentOrganization->paymentProfileActivated())
    <div class="alert alert-warning border-0 shadow-sm">
        <div class="d-flex align-items-center"><i class="bi bi-credit-card-2-front fs-3 me-3"></i>
            <div class="flex-grow-1"><strong>Online payments are not active yet.</strong>
                <div>Everything else is live: routers, plans, vouchers and staff access all work. Activate your payment profile to let customers pay from the portal.</div>
            </div>
            <a href="{{ route('settings.index') }}" class="btn btn-hotfii ms-3 flex-shrink-0">Activate payments</a>
        </div>
    </div>
@endif

{{-- Money over time, then the fleet's state right now. --}}
<div class="row g-4 mb-4">
    <div class="col-xxl-8">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="hf-chart-eyebrow">Last 14 days</span>
                    <h2 class="h5 mb-0">Revenue collected</h2>
                </div>
                <div class="text-end">
                    <span class="hf-chart-eyebrow">Total</span>
                    <div class="fs-5 fw-bold">₦{{ number_format($revenue['total'], 0) }}</div>
                </div>
            </div>
            <div class="card-body pt-2 px-3 pb-3">
                @if($revenue['total'] > 0)
                    <div id="hf-revenue-chart" class="hf-chart" role="img"
                         aria-label="Revenue per day for the last 14 days, in naira."
                         data-labels='@json($revenue['labels'])'
                         data-values='@json($revenue['values'])'></div>
                    {{-- The table view. Every plotted value is readable without a tooltip. --}}
                    <table class="visually-hidden">
                        <caption>Revenue by day</caption>
                        <thead><tr><th scope="col">Day</th><th scope="col">Revenue</th></tr></thead>
                        <tbody>
                            @foreach($revenue['labels'] as $index => $label)
                                <tr><th scope="row">{{ $label }}</th><td>₦{{ number_format($revenue['values'][$index], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-graph-up-arrow fs-1 d-block mb-2 opacity-50"></i>
                        No payments collected in the last 14 days. Sales will chart here as they land.
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between align-items-start gap-2">
                <div>
                    <span class="hf-chart-eyebrow">Right now</span>
                    <h2 class="h5 mb-0">Router fleet</h2>
                </div>
                <a href="{{ route('network.devices.index') }}" class="small">Manage</a>
            </div>
            <div class="card-body pt-2 px-4 pb-4">
                @if($fleet['total'] > 0)
                    <div id="hf-fleet-chart" role="img"
                         aria-label="Routers by state: {{ collect($fleet['slices'])->map(fn ($slice) => $slice['value'].' '.$slice['label'])->join(', ', ' and ') }}."
                         data-slices='@json($fleet['slices'])'></div>
                    {{-- Doubles as the legend and the table view: state, icon and count
                         are printed, so a fill is never the only way to read a value. --}}
                    <div class="hf-legend mt-3">
                        @foreach($fleet['rows'] as $row)
                            <div class="hf-legend-row @if($row['value'] === 0) is-empty @endif">
                                <span class="hf-legend-key" style="background: var(--hf-status-{{ $row['tone'] }})"></span>
                                <i class="bi bi-{{ $row['icon'] }} text-secondary"></i>
                                <span class="flex-grow-1">{{ $row['label'] }}</span>
                                <span class="hf-legend-count fw-semibold">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-router fs-1 d-block mb-2 opacity-50"></i>
                        No routers yet. Add your first location and network device to see fleet health here.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- When the day is busy, and what people are buying. --}}
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="hf-chart-eyebrow">Today, by hour</span>
                    <h2 class="h5 mb-0">Session starts</h2>
                </div>
                @if($hourly['peak'])
                    <div class="text-end">
                        <span class="hf-chart-eyebrow">Busiest hour</span>
                        <div class="fs-5 fw-bold">{{ str_pad($hourly['peak']['hour'], 2, '0', STR_PAD_LEFT) }}:00</div>
                    </div>
                @endif
            </div>
            <div class="card-body pt-2 px-3 pb-3">
                @if($hourly['total'] > 0)
                    <div id="hf-hourly-chart" class="hf-chart-sm" role="img"
                         aria-label="Sessions started in each hour of today."
                         data-labels='@json($hourly['labels'])'
                         data-values='@json($hourly['values'])'></div>
                    <table class="visually-hidden">
                        <caption>Session starts by hour today</caption>
                        <thead><tr><th scope="col">Hour</th><th scope="col">Sessions</th></tr></thead>
                        <tbody>
                            @foreach($hourly['labels'] as $index => $label)
                                <tr><th scope="row">{{ $label }}:00</th><td>{{ $hourly['values'][$index] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-clock-history fs-1 d-block mb-2 opacity-50"></i>
                        No sessions started today yet.
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="hf-chart-eyebrow">Last {{ $plans['days'] }} days</span>
                    <h2 class="h5 mb-0">Top plans by revenue</h2>
                </div>
                <a href="{{ route('reports.index') }}" class="small">Full report</a>
            </div>
            <div class="card-body pt-2 px-3 pb-3">
                @if($plans['labels'] !== [])
                    {{-- Bars are labelled at the tip, so the value rides the mark; the
                         table below is for readers who never see the mark at all. --}}
                    <div id="hf-plans-chart" class="hf-chart-sm" role="img"
                         aria-label="Revenue per plan over the last {{ $plans['days'] }} days."
                         data-labels='@json($plans['labels'])'
                         data-values='@json($plans['values'])'></div>
                    <table class="visually-hidden">
                        <caption>Revenue by plan, last {{ $plans['days'] }} days</caption>
                        <thead><tr><th scope="col">Plan</th><th scope="col">Revenue</th></tr></thead>
                        <tbody>
                            @foreach(array_reverse($plans['labels']) as $index => $label)
                                <tr><th scope="row">{{ $label }}</th><td>₦{{ number_format(array_reverse($plans['values'])[$index], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        No plan sales in the last {{ $plans['days'] }} days.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between"><h2 class="h5 mb-0">Network health</h2><a href="{{ route('network.devices.index') }}">View all</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Device</th><th>Location</th><th>Vendor</th><th>Status</th></tr></thead>
                <tbody>@forelse($devices as $device)
                    <tr><td><a href="{{ route('network.devices.show', $device) }}" class="fw-semibold text-decoration-none">{{ $device->name }}</a></td><td>{{ $device->location->name }}</td><td>{{ $device->vendor->label() }}</td><td><span class="status-dot status-{{ $device->status->value === 'online' ? 'online' : ($device->status->value === 'offline' ? 'offline' : 'pending') }}"></span>{{ ucfirst($device->status->value) }}</td></tr>
                @empty<tr><td colspan="4" class="text-center py-5 text-secondary">No routers yet. Add your first location and network device.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4"><h2 class="h5 mb-0">Recent transactions</h2></div>
            <div class="list-group list-group-flush">@forelse($transactions as $transaction)
                <div class="list-group-item px-4 py-3 d-flex justify-content-between"><div><strong>{{ $transaction->reference }}</strong><div class="small text-secondary">{{ $transaction->created_at->diffForHumans() }}</div></div><div class="text-end"><strong>₦{{ number_format($transaction->gross_amount_kobo / 100, 0) }}</strong><div class="small text-secondary">{{ ucfirst($transaction->status->value) }}</div></div></div>
            @empty<div class="p-5 text-center text-secondary">Transactions will appear here.</div>@endforelse</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush
