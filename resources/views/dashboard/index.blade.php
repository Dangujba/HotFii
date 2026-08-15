@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.strtok(auth()->user()->name, ' '))
@section('subheading', 'A live view of '.$currentOrganization->name)
@section('actions')<a href="{{ route('network.devices.index') }}" class="btn btn-hotfii"><i class="bi bi-plus-lg me-1"></i>Add network device</a>@endsection
@section('content')
<livewire:dashboard-pulse :organization-uuid="$currentOrganization->uuid" />

@if($currentOrganization->status->value === 'sandbox')
    <div class="alert alert-warning border-0 shadow-sm">
        <div class="d-flex"><i class="bi bi-flask fs-3 me-3"></i><div><strong>Your organization is in sandbox mode.</strong><div>Connect a router and run the readiness test. Payment review is needed only before live collections.</div></div></div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card metric-card">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between"><h2 class="h5 mb-0">Network health</h2><a href="{{ route('network.devices.index') }}">View all</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Device</th><th>Location</th><th>Vendor</th><th>Status</th></tr></thead>
                <tbody>@forelse($devices as $device)
                    <tr><td><a href="{{ route('network.devices.show', $device) }}" class="fw-semibold text-decoration-none">{{ $device->name }}</a></td><td>{{ $device->location->name }}</td><td>{{ $device->vendor->label() }}</td><td><span class="status-dot status-{{ $device->status->value === 'online' ? 'online' : ($device->status->value === 'offline' ? 'offline' : 'pending') }}"></span>{{ ucfirst($device->status->value) }}</td></tr>
                @empty<tr><td colspan="4" class="text-center py-5 text-secondary">No routers yet. Add your first location and network device.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card metric-card">
            <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 mb-0">Recent transactions</h2></div>
            <div class="list-group list-group-flush">@forelse($transactions as $transaction)
                <div class="list-group-item px-4 py-3 d-flex justify-content-between"><div><strong>{{ $transaction->reference }}</strong><div class="small text-secondary">{{ $transaction->created_at->diffForHumans() }}</div></div><div class="text-end"><strong>₦{{ number_format($transaction->gross_amount_kobo / 100, 0) }}</strong><div class="small text-secondary">{{ ucfirst($transaction->status->value) }}</div></div></div>
            @empty<div class="p-5 text-center text-secondary">Transactions will appear here.</div>@endforelse</div>
        </div>
    </div>
</div>
@endsection