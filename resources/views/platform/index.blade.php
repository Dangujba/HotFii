@extends('layouts.platform')
@section('title', 'Overview')
@section('heading', 'Platform Overview')
@section('subheading', 'Every tenant, every naira, across the whole deployment')
@section('actions')<a href="{{ route('platform.organizations.index') }}" class="btn btn-hotfii"><i class="bi bi-buildings me-1"></i>All organizations</a>@endsection
@section('content')

<div class="row g-3 mb-4">
    @foreach([
        ['Organizations', number_format($stats['organizations']), 'buildings', 'primary', route('platform.organizations.index')],
        ['Customers', number_format($stats['customers']), 'people', 'info', route('platform.users.index')],
        ['Routers', number_format($stats['routers']), 'router', 'primary', route('platform.routers.index')],
        ['Online routers', number_format($stats['online_routers']), 'wifi', 'success', route('platform.routers.index', ['status' => 'online'])],
        ['Live sessions', number_format($stats['live_sessions']), 'broadcast', 'success', route('platform.routers.index')],
        ['Vouchers', number_format($stats['vouchers']), 'ticket-perforated', 'warning', route('platform.organizations.index')],
        ['Data consumed', \App\Support\Bytes::human($stats['data_bytes']), 'cloud-arrow-down', 'info', route('platform.routers.index')],
        ['Volume this month', \App\Support\Naira::from($stats['monthly_volume']), 'cash-stack', 'primary', route('platform.transactions.index')],
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


{{-- Platform-wide network, voucher and billing health. --}}
<div class="row g-4 mb-4">

    <div class="col-xl-4">
        <div class="card metric-card h-100">

            <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between">
                <div>
                    <span class="hf-chart-eyebrow">Network</span>
                    <h2 class="h5 mb-0">Router status</h2>
                </div>

                <a
                    href="{{ route('platform.routers.index') }}"
                    class="small"
                >
                    All routers
                </a>
            </div>

            <div class="card-body">

                @foreach($routerStatus['rows'] as $row)

                    <a
                        href="{{
                            route(
                                'platform.routers.index',
                                ['status' => $row['value']]
                            )
                        }}"
                        class="d-flex justify-content-between align-items-center text-decoration-none text-reset py-2 border-bottom"
                    >
                        <span>
                            {{ $row['label'] }}
                        </span>

                        <strong>
                            {{ number_format($row['count']) }}
                        </strong>
                    </a>

                @endforeach

                @if(count($vendorMix))

                    <div class="small text-secondary mt-3 mb-2">
                        Vendors
                    </div>

                    @foreach($vendorMix as $vendor)

                        <a
                            href="{{
                                route(
                                    'platform.routers.index',
                                    ['vendor' => $vendor['value']]
                                )
                            }}"
                            class="d-flex justify-content-between small text-decoration-none text-reset py-1"
                        >
                            <span>
                                {{ $vendor['label'] }}
                            </span>

                            <strong>
                                {{ $vendor['count'] }}
                            </strong>
                        </a>

                    @endforeach

                @endif

            </div>

        </div>
    </div>


    <div class="col-xl-4">
        <div class="card metric-card h-100">

            <div class="card-header border-0 pt-4 px-4">
                <span class="hf-chart-eyebrow">
                    Access
                </span>

                <h2 class="h5 mb-0">
                    Voucher lifecycle
                </h2>
            </div>

            <div class="card-body">

                @foreach($voucherStatus['rows'] as $row)

                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">

                        <span>
                            {{ $row['label'] }}
                        </span>

                        <strong>
                            {{ number_format($row['count']) }}
                        </strong>

                    </div>

                @endforeach

                <div class="row g-2 mt-2">

                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="small text-secondary">
                                Activated today
                            </div>

                            <div class="fw-bold fs-5">
                                {{
                                    number_format(
                                        $voucherStatus[
                                            'activated_today'
                                        ]
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="small text-secondary">
                                Complimentary
                            </div>

                            <div class="fw-bold fs-5">
                                {{
                                    number_format(
                                        $voucherStatus[
                                            'complimentary'
                                        ]
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>


    <div class="col-xl-4">
        <div class="card metric-card h-100">

            <div class="card-header border-0 pt-4 px-4">
                <span class="hf-chart-eyebrow">
                    Accounting
                </span>

                <h2 class="h5 mb-0">
                    Network consumption
                </h2>
            </div>

            <div class="card-body">

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Upload</span>
                    <strong>
                        {{
                            \App\Support\Bytes::human(
                                $network['input_bytes']
                            )
                        }}
                    </strong>
                </div>

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Download</span>
                    <strong>
                        {{
                            \App\Support\Bytes::human(
                                $network['output_bytes']
                            )
                        }}
                    </strong>
                </div>

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Total consumed</span>
                    <strong>
                        {{
                            \App\Support\Bytes::human(
                                $network['total_bytes']
                            )
                        }}
                    </strong>
                </div>

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Live sessions</span>
                    <strong>
                        {{
                            number_format(
                                $network['live_sessions']
                            )
                        }}
                    </strong>
                </div>

                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Total sessions</span>
                    <strong>
                        {{
                            number_format(
                                $network['sessions_total']
                            )
                        }}
                    </strong>
                </div>

                <div class="d-flex justify-content-between py-2">
                    <span>Sessions started today</span>
                    <strong>
                        {{
                            number_format(
                                $network['sessions_today']
                            )
                        }}
                    </strong>
                </div>

            </div>

        </div>
    </div>

</div>


<div class="row g-3 mb-4">

    @foreach([
        ['Collecting payments', number_format($stats['collecting']), 'broadcast-pin', 'success', route('platform.organizations.index', ['collecting' => 'yes'])],
        ['Fees this month', \App\Support\Naira::from($stats['monthly_fees']), 'percent', 'primary', route('platform.billing.index')],
        ['Invoices outstanding', \App\Support\Naira::from($stats['open_invoices']), 'receipt', 'warning', route('platform.billing.index', ['invoice_status' => 'open'])],
        ['Payment reviews', number_format($stats['pending_reviews']), 'person-check', $stats['pending_reviews'] ? 'danger' : 'secondary', route('platform.reviews.index')],
    ] as [$label, $value, $icon, $tone, $href])

        <div class="col-sm-6 col-xl-3">

            <a
                href="{{ $href }}"
                class="text-decoration-none text-reset"
            >

                <div class="card metric-card h-100">

                    <div class="card-body">

                        <div class="text-secondary small">
                            {{ $label }}
                        </div>

                        <div class="fs-4 fw-bold">
                            {{ $value }}
                        </div>

                        <i class="bi bi-{{ $icon }} text-{{ $tone }}"></i>

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


<div class="row g-4 mb-4">

    <div class="col-xl-7">

        <div class="card metric-card h-100">

            <div class="card-header d-flex justify-content-between align-items-center">

                <h2 class="h5 mb-0">
                    Top organizations
                </h2>

                <a
                    href="{{ route('platform.organizations.index') }}"
                    class="small"
                >
                    All organizations
                </a>

            </div>

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table mb-0">

                        <thead>
                        <tr>
                            <th>Organization</th>
                            <th class="text-end">Routers</th>
                            <th class="text-end">Online</th>
                            <th class="text-end">Live</th>
                            <th class="text-end">Data</th>
                            <th class="text-end">Volume</th>
                        </tr>
                        </thead>

                        <tbody>

                        @forelse($topOrganizations as $organization)

                            <tr>

                                <td>
                                    <a
                                        href="{{
                                            route(
                                                'platform.organizations.show',
                                                $organization
                                            )
                                        }}"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ $organization->name }}
                                    </a>
                                </td>

                                <td class="text-end">
                                    {{ number_format($organization->routers_count) }}
                                </td>

                                <td class="text-end">
                                    {{ number_format($organization->online_routers_count) }}
                                </td>

                                <td class="text-end">
                                    {{ number_format($organization->live_sessions_count) }}
                                </td>

                                <td class="text-end">
                                    {{
                                        \App\Support\Bytes::human(
                                            (int) $organization->data_bytes
                                        )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        \App\Support\Naira::from(
                                            (int) $organization->volume_kobo
                                        )
                                    }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="6"
                                    class="text-center py-4 text-secondary"
                                >
                                    No organization activity yet.
                                </td>
                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>


    <div class="col-xl-5">

        <div class="card metric-card h-100">

            <div class="card-header d-flex justify-content-between align-items-center">

                <h2 class="h5 mb-0">
                    Recent router heartbeats
                </h2>

                <a
                    href="{{ route('platform.routers.index') }}"
                    class="small"
                >
                    Router network
                </a>

            </div>

            <div class="list-group list-group-flush">

                @forelse($recentRouters as $router)

                    @php
                        $routerTone = match(
                            $router->status->value
                        ) {
                            'online' => 'success',
                            'testing' => 'info',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary',
                        };
                    @endphp

                    <div class="list-group-item px-4 py-3">

                        <div class="d-flex justify-content-between gap-3">

                            <div>

                                <strong>
                                    {{ $router->name }}
                                </strong>

                                <div class="small text-secondary">

                                    {{
                                        $router
                                            ->organization
                                            ?->name
                                        ?? 'Unknown organization'
                                    }}

                                    · {{ $router->vendor->label() }}

                                    · {{
                                        number_format(
                                            $router->active_sessions_count
                                        )
                                    }} live

                                </div>

                            </div>

                            <div class="text-end">

                                <span
                                    class="badge text-bg-{{ $routerTone }}"
                                >
                                    {{
                                        ucfirst(
                                            $router->status->value
                                        )
                                    }}
                                </span>

                                <div class="small text-secondary mt-1">
                                    {{
                                        $router->last_heartbeat_at
                                            ?->diffForHumans()
                                        ?? 'Never'
                                    }}
                                </div>

                            </div>

                        </div>

                    </div>

                @empty

                    <div class="p-5 text-center text-secondary">
                        No routers registered yet.
                    </div>

                @endforelse

            </div>

        </div>

    </div>

</div>

<div class="card metric-card">
    <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Latest payments</h2><a href="{{ route('platform.transactions.index') }}" class="small">All transactions</a></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Organization</th><th>Router</th><th class="text-end">Amount</th><th class="text-end">Fee</th><th>Status</th><th>When</th></tr></thead>
        <tbody>@forelse($transactions as $transaction)
            <tr>
                <td><code>{{ $transaction->reference }}</code></td>
                <td>@if($transaction->organization)<a class="text-decoration-none" href="{{ route('platform.organizations.show', $transaction->organization) }}">{{ $transaction->organization->name }}</a>@else<span class="text-secondary">—</span>@endif</td>
                <td>{{ $transaction->networkDevice?->name ?? 'Unattributed' }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->gross_amount_kobo) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->platform_fee_kobo) }}</td>
                <td><span class="badge text-bg-{{ $transaction->status->value === 'successful' ? 'success' : ($transaction->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($transaction->status->value) }}</span></td>
                <td>{{ $transaction->created_at->diffForHumans() }}</td>
            </tr>
        @empty<tr><td colspan="7" class="text-center py-5 text-secondary">No transactions yet.</td></tr>@endforelse</tbody>
    </table></div></div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/platform.js')
@endpush
