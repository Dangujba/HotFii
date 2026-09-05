@extends('layouts.app')
@section('title', 'Live Sessions')
@section('heading', 'Live Sessions')
@section('subheading', 'RADIUS accounting activity updates every 15 seconds when live')
@section('content')
<div class="card metric-card">
<x-filter-bar :action="route('sessions.index')" :active="$filtered">
    <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Username, MAC, or IP"></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="device"><option value="">All routers</option>@foreach($devices as $device)<option value="{{ $device->id }}" @selected($filters['device'] === $device->id)>{{ $device->name }}</option>@endforeach</select></div>
</x-filter-bar>
<div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>User</th><th>Router</th><th>Device / IP</th><th>Usage</th><th>Started</th><th>Status</th><th></th></tr></thead>
<tbody>@forelse($sessions as $session)<tr>
<td><strong>{{ $session->customer?->name ?: ($session->radius_username ?: ($session->mac_address ?: 'Guest')) }}</strong><div class="small text-secondary">{{ $session->accessPlan?->name }}</div></td>
<td>{{ $session->networkDevice->name }}</td><td><code>{{ $session->mac_address ?: 'Unknown MAC' }}</code><div class="small text-secondary">{{ $session->ip_address }}</div></td>
<td>{{ \App\Support\Bytes::human($session->totalBytes()) }}</td><td>{{ $session->started_at?->diffForHumans() }}</td>
<td><span class="badge text-bg-{{ $session->status === 'active' ? 'success' : ($session->status === 'disconnect_pending' ? 'warning' : 'secondary') }}">{{ str_replace('_', ' ', ucfirst($session->status)) }}</span></td>
<td>@if(in_array($session->status, ['active','disconnect_pending'], true))<form method="POST" action="{{ route('sessions.disconnect', $session) }}">@csrf<button class="btn btn-sm btn-outline-danger" data-confirm-title="Disconnect {{ $session->customer?->name ?: ($session->radius_username ?: ($session->mac_address ?: 'Guest')) }}?" data-confirm="A disconnect request is sent to {{ $session->networkDevice->name }} and the session will be terminated. Remaining plan time is not refunded." data-confirm-icon="danger" data-confirm-button="Disconnect now">Disconnect</button></form>@endif</td>
</tr>@empty<tr><td colspan="7" class="text-center py-5 text-secondary">{{ $filtered ? 'No sessions match these filters.' : 'Accounting sessions will appear after a router sends Accounting-Start.' }}</td></tr>@endforelse</tbody>
</table></div></div></div><div class="mt-3">{{ $sessions->links() }}</div>
@endsection