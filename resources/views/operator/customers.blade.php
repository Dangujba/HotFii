@extends('layouts.app')
@section('title', 'Customers & Identities')
@section('heading', $currentOrganization->mode->value === 'commerce' ? 'Customer Directory' : 'People & Access Identities')
@section('subheading', 'Employees, students, contractors, guests, and hotspot customers')
@section('content')
@if(session('issued_credential'))
<div class="alert alert-success">
    <strong>Save these credentials now.</strong> They are shown only in this confirmation.
    <div class="row g-2 mt-2"><div class="col-md-6"><div class="input-group"><span class="input-group-text">Username</span><input class="form-control font-monospace" readonly value="{{ session('issued_credential.username') }}"></div></div><div class="col-md-6"><div class="input-group"><span class="input-group-text">Password</span><input class="form-control font-monospace" readonly value="{{ session('issued_credential.password') }}"></div></div></div>
</div>
@endif
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card metric-card">
        <x-filter-bar :action="route('customers.index')" :active="$filtered">
            <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Name, email, or phone"></div>
            <div class="col-md-3"><select class="form-select form-select-sm" name="type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select form-select-sm" name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        </x-filter-bar>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Person</th><th>Type</th><th>Status</th><th>Credential</th><th>Last access</th></tr></thead>
            <tbody>@forelse($customers as $customer)<tr><td><strong>{{ $customer->name ?: 'Guest customer' }}</strong><div class="small text-secondary">{{ $customer->email ?: $customer->phone }}</div></td><td>{{ ucfirst($customer->type) }}</td><td><span class="badge text-bg-{{ $customer->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($customer->status) }}</span></td><td>@if($customer->currentCredential)<code>{{ $customer->currentCredential->username }}</code><div class="small text-secondary">{{ $customer->currentCredential->accessPlan?->name }}</div>@else<span class="text-secondary">None</span>@endif</td><td>{{ $customer->last_authenticated_at?->diffForHumans() ?? 'Never' }}</td></tr>@empty<tr><td colspan="5" class="text-center py-5 text-secondary">{{ $filtered ? 'No identities match these filters.' : 'No identities yet.' }}</td></tr>@endforelse</tbody>
        </table></div></div></div><div class="mt-3">{{ $customers->links() }}</div>
    </div>
    <div class="col-xl-4"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Create identity</h2></div><div class="card-body">
        @if($plans->isEmpty())<div class="alert alert-warning mb-0">Create a free or internal access plan before issuing staff credentials.</div>@else
        <form method="POST" action="{{ route('customers.store') }}">@csrf
            <div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
            <div class="row g-3"><div class="col-6"><label class="form-label">Identity type</label><select class="form-select" name="type"><option value="employee">Employee</option><option value="student">Student</option><option value="contractor">Contractor</option><option value="guest">Guest</option><option value="customer">Customer</option></select></div><div class="col-6"><label class="form-label">Access policy</label><select class="form-select" name="access_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></div></div>
            <div class="row g-3 mt-0"><div class="col-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div><div class="col-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div></div>
            <div class="row g-3 mt-0"><div class="col-6"><label class="form-label">Username</label><input class="form-control" name="username" placeholder="Auto-generate"></div><div class="col-6"><label class="form-label">Password</label><input class="form-control" name="password" placeholder="Auto-generate"></div></div>
            <div class="mt-3"><label class="form-label">Expires at</label><input class="form-control" type="datetime-local" name="expires_at"></div>
            <button class="btn btn-hotfii w-100 mt-4">Create and sync to RADIUS</button>
        </form>
        @endif
    </div></div></div>
</div>
@endsection