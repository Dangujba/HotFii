@extends('layouts.platform')
@section('title', 'Audit log')
@section('heading', 'Audit log')
@section('subheading', 'Every recorded action across every tenant, newest first')
@section('content')

<div class="card metric-card">
    <x-filter-bar :action="route('platform.audit.index')" :active="$filtered">
        <div class="col-md-3"><select class="form-select form-select-sm" name="action">
            <option value="">Any action</option>
            @foreach($actions as $action)<option value="{{ $action }}" @selected($filters['action'] === $action)>{{ str_replace('.', ' · ', $action) }}</option>@endforeach
        </select></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="organization">
            <option value="">All organizations</option>
            @foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected($filters['organization'] === $organization->id)>{{ $organization->name }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="actor" value="{{ $filters['actor'] }}" placeholder="Actor"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="date" name="from" value="{{ $filters['from'] }}" aria-label="From date"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="date" name="to" value="{{ $filters['to'] }}" aria-label="To date"></div>
    </x-filter-bar>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Organization</th><th>Reason</th><th>Change</th></tr></thead>
        <tbody>@forelse($logs as $log)
            <tr>
                <td class="small text-nowrap">{{ $log->created_at->format('j M Y, H:i') }}<div class="text-secondary">{{ $log->created_at->diffForHumans() }}</div></td>
                <td class="small"><strong>{{ str_replace('.', ' · ', $log->action) }}</strong>
                    @if($log->subject_type)<div class="text-secondary">{{ class_basename($log->subject_type) }}@if($log->subject_id) #{{ $log->subject_id }}@endif</div>@endif
                </td>
                {{-- Null actor means the row was written by a console command or a
                     job, not a person — the same "System" convention the operator
                     settings page uses. --}}
                <td class="small">{{ $log->user?->name ?? 'System' }}
                    <div class="text-secondary">{{ $log->user?->email ?? ($log->ip_address ?: '—') }}</div>
                </td>
                {{-- A soft-deleted organization does not load through the
                     relation, so the id is what keeps its rows from reading as
                     platform-level actions. --}}
                <td class="small">@if($log->organization)<a class="text-decoration-none" href="{{ route('platform.organizations.show', $log->organization) }}">{{ $log->organization->name }}</a>@elseif($log->organization_id)<span class="text-secondary">Deleted organization #{{ $log->organization_id }}</span>@else<span class="text-secondary">Platform</span>@endif</td>
                <td class="small">{{ $log->reason ?: '—' }}</td>
                <td class="small">
                    @if($log->before || $log->after)
                        <details>
                            <summary class="text-secondary" style="cursor: pointer;">Before / after</summary>
                            <pre class="small mb-0 mt-2">{{ json_encode(['before' => $log->before, 'after' => $log->after], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @else
                        <span class="text-secondary">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-5 text-secondary">{{ $filtered ? 'No audited actions match these filters.' : 'Nothing has been audited yet.' }}</td></tr>
        @endforelse</tbody>
    </table></div></div>
    <div class="card-footer small text-secondary"><i class="bi bi-lock me-1"></i>Append-only. Rows are written by payment review, support impersonation, suspension and <code>hotfii:create-admin</code>, and cannot be edited or deleted from the application.</div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
