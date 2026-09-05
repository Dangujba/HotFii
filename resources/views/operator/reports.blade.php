@extends('layouts.app')
@section('title', 'Reports')
@section('heading', 'Reports')
@section('subheading', 'Revenue, access usage, and plan performance')
@section('actions')
    <a class="btn btn-hotfii" href="{{ route('reports.export.pdf', request()->only('from','to')) }}" download><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
    <a class="btn btn-outline-secondary" href="{{ route('reports.export', request()->only('from','to')) }}" download><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
@endsection
@section('content')
<div class="card metric-card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
            <div class="col-md-4"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
            <div class="col-md-4"><button class="btn btn-hotfii w-100"><i class="bi bi-funnel me-1"></i>Apply date range</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary">Gross paid sales</div><div class="fs-3 fw-bold">₦{{ number_format(($summary->gross_kobo ?? 0) / 100, 0) }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary">Paid activations</div><div class="fs-3 fw-bold">{{ number_format($summary->sales ?? 0) }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary">Access sessions</div><div class="fs-3 fw-bold">{{ number_format($usage->sessions ?? 0) }}</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary">Transferred data</div><div class="fs-3 fw-bold">{{ \App\Support\Bytes::human($usage->bytes ?? 0) }}</div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4"><span class="hf-chart-eyebrow">{{ $from->format('j M') }} – {{ $to->format('j M Y') }}</span><h2 class="h5 mb-0">Daily paid sales by channel</h2></div>
            <div class="card-body pt-2 px-3 pb-3">
                <div id="hf-report-sales-chart" class="hf-chart" role="img" aria-label="Daily paid sales split between online, voucher, and direct cash channels."
                     data-labels='@json($salesTrend['labels'])' data-series='@json($salesTrend['series'])'></div>
                <table class="visually-hidden"><caption>Daily paid sales by channel</caption><thead><tr><th>Date</th><th>Online</th><th>Vouchers</th><th>Direct cash</th></tr></thead><tbody>
                    @foreach($salesTrend['labels'] as $index => $label)<tr><th>{{ $label }}</th><td>{{ $salesTrend['series']['online'][$index] }}</td><td>{{ $salesTrend['series']['voucher'][$index] }}</td><td>{{ $salesTrend['series']['cash'][$index] }}</td></tr>@endforeach
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4"><span class="hf-chart-eyebrow">Successful sales</span><h2 class="h5 mb-0">Revenue mix</h2></div>
            <div class="card-body pt-2 px-4 pb-4">
                <div id="hf-report-channel-chart" class="hf-chart-sm" role="img" aria-label="Revenue share by sales channel."
                     data-labels='@json($channels->pluck('label'))' data-values='@json($channels->pluck('value'))'></div>
                <div class="hf-legend mt-2">
                    @foreach($channels as $index => $channel)
                        <div class="hf-legend-row @if($channel['value'] == 0) is-empty @endif"><span class="hf-legend-key" style="background: var(--hf-chart-{{ $index + 1 }})"></span><span class="flex-grow-1">{{ $channel['label'] }}</span><span class="text-end"><strong>₦{{ number_format($channel['value'], 0) }}</strong><span class="d-block small text-secondary">{{ number_format($channel['sales']) }} sales</span></span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4"><span class="hf-chart-eyebrow">Highest revenue first</span><h2 class="h5 mb-0">Top plans</h2></div>
            <div class="card-body pt-2 px-3 pb-3">
                <div id="hf-report-plans-chart" class="hf-chart" role="img" aria-label="Top plans ranked by paid revenue."
                     data-labels='@json($topPlans->pluck('name')->reverse()->values())' data-values='@json($topPlans->pluck('total')->reverse()->values()->map(fn ($value) => round($value / 100, 2)))'></div>
                <table class="visually-hidden"><caption>Top plans by revenue</caption><thead><tr><th>Plan</th><th>Sales</th><th>Revenue</th></tr></thead><tbody>
                    @foreach($topPlans as $plan)<tr><th>{{ $plan->name }}</th><td>{{ $plan->sales }}</td><td>{{ $plan->total / 100 }}</td></tr>@endforeach
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header border-0 pt-4 px-4"><span class="hf-chart-eyebrow">Daily network activity</span><h2 class="h5 mb-0">Sessions and data usage</h2></div>
            <div class="card-body pt-2 px-3 pb-3">
                <div id="hf-report-usage-chart" class="hf-chart" role="img" aria-label="Daily access sessions and transferred data."
                     data-labels='@json($usageTrend['labels'])' data-sessions='@json($usageTrend['sessions'])' data-megabytes='@json($usageTrend['megabytes'])'></div>
                <table class="visually-hidden"><caption>Daily sessions and data usage</caption><thead><tr><th>Date</th><th>Sessions</th><th>Megabytes</th></tr></thead><tbody>
                    @foreach($usageTrend['labels'] as $index => $label)<tr><th>{{ $label }}</th><td>{{ $usageTrend['sessions'][$index] }}</td><td>{{ $usageTrend['megabytes'][$index] }}</td></tr>@endforeach
                </tbody></table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
    @vite('resources/js/reports.js')
@endpush
