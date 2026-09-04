@extends('layouts.platform')
@section('title', 'System health')
@section('heading', 'System health')
@section('subheading', 'Runtime state of the deployment: queues, workers, webhooks and configuration')
@section('content')

<div class="row g-3 mb-4">
    @foreach([
        ['Redis', ucfirst($health['redis']), $health['redis'] === 'online' ? 'success' : 'danger'],
        ['Broadcasting', ucfirst($health['reverb']), $health['reverb'] === 'configured' ? 'success' : 'secondary'],
        ['Queued jobs', number_format($health['queued_jobs']), $health['queued_jobs'] > 100 ? 'warning' : 'secondary'],
        ['Failed jobs', number_format($health['failed_jobs']), $health['failed_jobs'] > 0 ? 'danger' : 'success'],
        ['Unprocessed webhooks', number_format($health['pending_webhooks']), $health['pending_webhooks'] > 0 ? 'warning' : 'success'],
    ] as [$label, $value, $tone])
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card metric-card h-100"><div class="card-body">
                <div class="text-secondary small">{{ $label }}</div>
                <div class="fs-5 fw-bold"><span class="status-dot bg-{{ $tone }}"></span>{{ $value }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        {{-- A date that has stopped moving is the cheapest evidence a worker or
             the scheduler has died, so these are dated rather than counted. --}}
        <div class="card metric-card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Last seen</h2></div>
            <div class="list-group list-group-flush">@foreach($heartbeats as $label => $timestamp)
                <div class="list-group-item d-flex justify-content-between gap-3">
                    <span>{{ $label }}</span>
                    <span class="text-end">
                        @if($timestamp)
                            <strong>{{ \Carbon\Carbon::parse($timestamp)->diffForHumans() }}</strong>
                            <div class="small text-secondary">{{ \Carbon\Carbon::parse($timestamp)->format('j M Y, H:i') }}</div>
                        @else
                            <span class="text-secondary">never</span>
                        @endif
                    </span>
                </div>
            @endforeach</div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card metric-card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Runtime</h2></div>
            <div class="list-group list-group-flush">@foreach($runtime as $label => $value)
                <div class="list-group-item d-flex justify-content-between gap-3">
                    <span>{{ $label }}</span>
                    <strong class="text-end {{ ($label === 'Debug mode' && $value === 'on') ? 'text-danger' : '' }}{{ ($label === 'Paystack mode' && $value === 'live') ? 'text-success' : '' }}">{{ $value }}</strong>
                </div>
            @endforeach</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="card metric-card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Queue depth</h2></div>
            <div class="list-group list-group-flush">@forelse($queues as $queue => $total)
                <div class="list-group-item d-flex justify-content-between"><code>{{ $queue }}</code><strong>{{ number_format($total) }}</strong></div>
            @empty
                <div class="list-group-item text-secondary py-4 text-center">Nothing waiting. Every dispatched job has been picked up.</div>
            @endforelse</div>
        </div>
    </div>
    <div class="col-xl-7">
        {{-- Accounts in a state that needs a human. Each links to the filtered
             list rather than making somebody go and find them. --}}
        <div class="card metric-card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Accounts needing attention</h2></div>
            <div class="list-group list-group-flush">@foreach($attention as $row)
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('platform.organizations.index', ['status' => $row['value']]) }}">
                    <span>{{ $row['label'] }}</span>
                    <span class="badge text-bg-{{ $row['count'] > 0 ? 'warning' : 'secondary' }}">{{ number_format($row['count']) }}</span>
                </a>
            @endforeach</div>
        </div>
    </div>
</div>

<div class="card metric-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Recent job failures</h2>
        @if($health['failed_jobs'] > count($failures))<span class="small text-secondary">{{ number_format($health['failed_jobs']) }} total</span>@endif
    </div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Job</th><th>Queue</th><th>Failed</th><th>Error</th></tr></thead>
        <tbody>@forelse($failures as $failure)
            <tr>
                <td class="small"><code>{{ $failure['job'] }}</code></td>
                <td class="small">{{ $failure['queue'] }}</td>
                <td class="small text-nowrap text-secondary">{{ $failure['failed_at'] ? \Carbon\Carbon::parse($failure['failed_at'])->diffForHumans() : '—' }}</td>
                <td class="small">{{ $failure['exception'] ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center py-5 text-secondary"><i class="bi bi-check2-circle fs-3 d-block mb-2"></i>No job has failed.</td></tr>
        @endforelse</tbody>
    </table></div></div>
    <div class="card-footer small text-secondary">
        <i class="bi bi-lock me-1"></i>Listed, not retried. Retrying a failed job re-runs money-handling code, so it stays a server command:
        <code>php artisan queue:retry all</code>.
    </div>
</div>

<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>Laravel native Redis workers are used until the Horizon package publishes Laravel 13 compatibility.</div>
@endsection
