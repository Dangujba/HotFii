@extends('layouts.app')
@section('title', 'Plans')
@section('heading', 'Plans & Access Policies')
@section('subheading', 'Reusable time, data, speed, and device limits')
@section('content')
<div class="row g-4">
    <div class="col-xl-8"><div class="card metric-card">
        <x-filter-bar :action="route('plans.index')" :active="$filtered">
            <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Plan name"></div>
            <div class="col-md-3"><select class="form-select form-select-sm" name="type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select form-select-sm" name="state"><option value="">Active and inactive</option><option value="active" @selected($filters['state'] === 'active')>Active only</option><option value="inactive" @selected($filters['state'] === 'inactive')>Inactive only</option></select></div>
        </x-filter-bar>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Name</th><th>Type</th><th>Price</th><th>Allowance</th><th>Speed</th><th>Devices</th></tr></thead>
        <tbody>@forelse($plans as $plan)<tr>
            <td class="fw-semibold">{{ $plan->name }}<div class="small fw-normal text-secondary">{{ $plan->is_active ? 'Active' : 'Inactive' }}</div></td>
            <td><span class="badge text-bg-{{ $plan->access_type === 'paid' ? 'success' : ($plan->access_type === 'internal' ? 'primary' : 'secondary') }}">{{ ucfirst($plan->access_type) }}</span></td>
            <td>{{ $plan->price_kobo ? '₦'.number_format($plan->price_kobo / 100, 0) : 'Free' }}</td>
            <td>@if($plan->duration_minutes){{ number_format($plan->duration_minutes) }} min @endif @if($plan->dataAllowance())<div>{{ $plan->dataAllowance() }}</div>@endif @if(!$plan->duration_minutes && !$plan->data_limit_bytes)Unlimited @endif</td>
            <td>{{ $plan->download_kbps ? number_format($plan->download_kbps / 1000, 1).' / '.number_format($plan->upload_kbps / 1000, 1).' Mbps' : 'Uncapped' }}</td>
            <td>{{ $plan->simultaneous_use }}</td>
        </tr>@empty<tr><td colspan="6" class="text-center py-5 text-secondary">{{ $filtered ? 'No plans match these filters.' : 'Create the first access plan.' }}</td></tr>@endforelse</tbody>
    </table></div></div></div><div class="mt-3">{{ $plans->links() }}</div></div>

    <div class="col-xl-4"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Create plan</h2></div><div class="card-body">
        <form method="POST" action="{{ route('plans.store') }}">@csrf
            <div class="mb-3"><label class="form-label">Plan name</label><input class="form-control" name="name" value="{{ old('name') }}" required placeholder="e.g. 2 Hours"></div>
            <div class="row g-3"><div class="col-6"><label class="form-label">Access type</label><select class="form-select" name="access_type"><option value="paid">Paid</option><option value="free">Free</option><option value="internal">Internal</option></select></div><div class="col-6"><label class="form-label">Price (₦)</label><input class="form-control" type="number" min="0" step="0.01" name="price_naira" value="{{ old('price_naira', 0) }}" required></div></div>
            <hr><div class="row g-3"><div class="col-6"><label class="form-label">Minutes</label><input class="form-control" type="number" min="1" name="duration_minutes"></div><div class="col-6"><label class="form-label">Data (MB)</label><input class="form-control" type="number" min="1" name="data_limit_mb"></div><div class="col-6"><label class="form-label">Download Kbps</label><input class="form-control" type="number" min="64" name="download_kbps"></div><div class="col-6"><label class="form-label">Upload Kbps</label><input class="form-control" type="number" min="64" name="upload_kbps"></div><div class="col-6"><label class="form-label">Devices</label><input class="form-control" type="number" min="1" max="20" name="simultaneous_use" value="1" required></div><div class="col-6"><label class="form-label">Validity days</label><input class="form-control" type="number" min="1" name="validity_days"></div></div>
            <button class="btn btn-hotfii w-100 mt-4">Create access plan</button>
        </form>
    </div></div></div>
</div>
@endsection