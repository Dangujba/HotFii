@extends('layouts.app')
@section('title', 'Reports')
@section('heading', 'Reports')
@section('subheading', 'Revenue, access usage, and plan performance')
@section('actions')<a class="btn btn-outline-success" href="{{ route('reports.export', request()->only('from','to')) }}"><i class="bi bi-download me-1"></i>Export CSV</a>@endsection
@section('content')
<div class="card metric-card mb-4"><div class="card-body"><form class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div><div class="col-md-4"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div><div class="col-md-4"><button class="btn btn-hotfii w-100">Apply date range</button></div></form></div></div>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="card metric-card"><div class="card-body"><div class="text-secondary">Sessions in period</div><div class="fs-3 fw-bold">{{ number_format($usage->sessions ?? 0) }}</div></div></div></div><div class="col-md-6"><div class="card metric-card"><div class="card-body"><div class="text-secondary">Transferred data</div><div class="fs-3 fw-bold">{{ number_format(($usage->bytes ?? 0) / 1073741824, 2) }} GB</div></div></div></div></div>
<div class="row g-4"><div class="col-xl-8"><div class="card metric-card"><div class="card-header bg-white"><h2 class="h5 mb-0">Daily paid sales</h2></div><div class="card-body"><div id="sales-chart" style="min-height:320px" data-series='@json($dailySales->pluck('total')->map(fn($value) => round($value / 100, 2))->values())' data-categories='@json($dailySales->pluck('day')->values())'></div></div></div></div><div class="col-xl-4"><div class="card metric-card"><div class="card-header bg-white"><h2 class="h5 mb-0">Top plans</h2></div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Plan</th><th>Sales</th><th>Value</th></tr></thead><tbody>@forelse($topPlans as $plan)<tr><td>{{ $plan->name }}</td><td>{{ $plan->sales }}</td><td>₦{{ number_format($plan->total / 100, 0) }}</td></tr>@empty<tr><td colspan="3" class="text-center py-5 text-secondary">No paid sales in range.</td></tr>@endforelse</tbody></table></div></div></div></div>
@endsection
@push('scripts')
@vite('resources/js/reports.js')
@endpush
