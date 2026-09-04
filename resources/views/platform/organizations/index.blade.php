@extends('layouts.platform')
@section('title', 'Organizations')
@section('heading', 'Organizations')
@section('subheading', 'Every tenant on the deployment')
@section('content')

<div class="card metric-card">
    <x-filter-bar :action="route('platform.organizations.index')" :active="$filtered">
        <div class="col-md-3"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Name or slug"></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="status">
            <option value="">Any status</option>
            @foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ str_replace('_', ' ', ucfirst($status->value)) }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="mode">
            <option value="">All modes</option>
            @foreach($modes as $mode)<option value="{{ $mode->value }}" @selected($filters['mode'] === $mode->value)>{{ ucfirst($mode->value) }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="plan">
            <option value="">Any plan</option>
            @foreach($plans as $plan)<option value="{{ $plan->value }}" @selected($filters['plan'] === $plan->value)>{{ str_replace('_', ' ', ucfirst($plan->value)) }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="collecting">
            <option value="">Live payments: any</option>
            <option value="yes" @selected($filters['collecting'] === 'yes')>Collecting</option>
            <option value="no" @selected($filters['collecting'] === 'no')>Not collecting</option>
        </select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="deleted">
            <option value="">Active accounts</option>
            <option value="yes" @selected($filters['deleted'] === 'yes')>Deleted accounts</option>
        </select></div>
    </x-filter-bar>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr>
            <th>Organization</th>
            <th>Status</th>
            <th>Plan</th>
            <th>Live payments</th>
            <th class="text-end">{{ $windowDays }}-day volume</th>
            <th class="text-end">Members</th>
            <th>Created</th>
        </tr></thead>
        <tbody>@forelse($organizations as $organization)
            <tr>
                <td>
                    <a class="text-decoration-none fw-semibold" href="{{ route('platform.organizations.show', $organization) }}">{{ $organization->name }}</a>
                    <div class="small text-secondary">{{ $organization->slug }} · {{ ucfirst($organization->mode->value) }}@if($organization->trashed()) · <span class="text-danger">deleted</span>@endif</div>
                </td>
                <td><span class="badge text-bg-{{ $organization->status === \App\Domain\Enums\OrganizationStatus::Suspended || $organization->status === \App\Domain\Enums\OrganizationStatus::PaymentRejected ? 'danger' : ($organization->status === \App\Domain\Enums\OrganizationStatus::Live ? 'success' : ($organization->status === \App\Domain\Enums\OrganizationStatus::Grace || $organization->status === \App\Domain\Enums\OrganizationStatus::PaymentReview ? 'warning' : 'secondary')) }}">{{ str_replace('_', ' ', ucfirst($organization->status->value)) }}</span></td>
                <td class="small">{{ str_replace('_', ' ', ucfirst($organization->billing_plan->value)) }}</td>
                <td class="small">
                    @if($organization->paymentProfileActivated())
                        <span class="status-dot bg-success"></span>On since {{ $organization->live_payments_enabled_at->format('j M Y') }}
                    @else
                        <span class="status-dot bg-secondary"></span><span class="text-secondary">Off</span>
                    @endif
                </td>
                <td class="text-end">{{ \App\Support\Naira::from((int) $organization->volume_kobo) }}</td>
                <td class="text-end">{{ $organization->users_count }}</td>
                <td class="small text-secondary">{{ $organization->created_at->format('j M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-5 text-secondary">{{ $filtered ? 'No organizations match these filters.' : 'No organizations have registered yet.' }}</td></tr>
        @endforelse</tbody>
    </table></div></div>
</div>
<div class="mt-3">{{ $organizations->links() }}</div>
@endsection
