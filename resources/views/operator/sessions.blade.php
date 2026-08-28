@extends('layouts.app')
@section('title', 'Live Sessions')
@section('heading', 'Live Sessions')
@section('subheading', 'RADIUS accounting activity updates every 15 seconds when live')
@section('content')
<div class="card metric-card"><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>User</th><th>Router</th><th>Device / IP</th><th>Usage</th><th>Started</th><th>Status</th><th></th></tr></thead>
<tbody>@forelse($sessions as $session)<tr>
<td><strong>{{ $session->customer?->name ?: $session->radius_username }}</strong><div class="small text-secondary">{{ $session->accessPlan?->name }}</div></td>
<td>{{ $session->networkDevice->name }}</td><td><code>{{ $session->mac_address ?: 'Unknown MAC' }}</code><div class="small text-secondary">{{ $session->ip_address }}</div></td>
<td>{{ number_format($session->totalBytes() / 1048576, 1) }} MB</td><td>{{ $session->started_at?->diffForHumans() }}</td>
<td><span class="badge text-bg-{{ $session->status === 'active' ? 'success' : ($session->status === 'disconnect_pending' ? 'warning' : 'secondary') }}">{{ str_replace('_', ' ', ucfirst($session->status)) }}</span></td>
<td>@if(in_array($session->status, ['active','disconnect_pending'], true))<form method="POST" action="{{ route('sessions.disconnect', $session) }}">@csrf<button class="btn btn-sm btn-outline-danger" data-confirm-title="Disconnect {{ $session->customer?->name ?: $session->radius_username }}?" data-confirm="A Change-of-Authorization is sent to {{ $session->networkDevice->name }} and the session drops immediately. Remaining plan time is not refunded." data-confirm-icon="danger" data-confirm-button="Disconnect now">Disconnect</button></form>@endif</td>
</tr>@empty<tr><td colspan="7" class="text-center py-5 text-secondary">Accounting sessions will appear after a router sends Accounting-Start.</td></tr>@endforelse</tbody>
</table></div></div></div><div class="mt-3">{{ $sessions->links() }}</div>
@endsection