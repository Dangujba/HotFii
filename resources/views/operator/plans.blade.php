@extends('layouts.app')
@section('title', 'Plans')
@section('heading', 'Plans & Access Policies')
@section('subheading', 'Reusable time, data, speed, and device limits')
@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card metric-card">
            <x-filter-bar :action="route('plans.index')" :active="$filtered">
                <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Plan name"></div>
                <div class="col-md-3"><select class="form-select form-select-sm" name="type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
                <div class="col-md-3"><select class="form-select form-select-sm" name="state"><option value="">Active and inactive</option><option value="active" @selected($filters['state'] === 'active')>Active only</option><option value="inactive" @selected($filters['state'] === 'inactive')>Inactive only</option></select></div>
            </x-filter-bar>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Allowance</th>
                                <th>Speed</th>
                                <th>Devices</th>
                                @if($canManagePlans)<th class="text-end">Actions</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($plans as $plan)
                            @php
                                $planUsed = $plan->voucher_batches_count
                                    + $plan->transactions_count
                                    + $plan->access_credentials_count
                                    + $plan->sessions_count > 0;
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $plan->name }}<div class="small fw-normal text-secondary">{{ $plan->is_active ? 'Active' : 'Inactive' }}</div></td>
                                <td><span class="badge text-bg-{{ $plan->access_type === 'paid' ? 'success' : ($plan->access_type === 'internal' ? 'primary' : 'secondary') }}">{{ ucfirst($plan->access_type) }}</span></td>
                                <td>{{ $plan->price_kobo ? '₦'.number_format($plan->price_kobo / 100, 0) : 'Free' }}</td>
                                <td>
                                    @if($plan->duration_minutes){{ number_format($plan->duration_minutes) }} min @endif
                                    @if($plan->dataAllowance())<div>{{ $plan->dataAllowance() }}</div>@endif
                                    @if(!$plan->duration_minutes && !$plan->data_limit_bytes)Unlimited @endif
                                    <div class="small text-secondary">{{ $plan->validityLabel() }}</div>
                                </td>
                                <td>{{ $plan->download_kbps || $plan->upload_kbps ? number_format(($plan->download_kbps ?: $plan->upload_kbps) / 1000, 1).' / '.number_format(($plan->upload_kbps ?: $plan->download_kbps) / 1000, 1).' Mbps' : 'Uncapped' }}</td>
                                <td>{{ $plan->simultaneous_use }}</td>
                                @if($canManagePlans)
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#edit-plan-{{ $plan->id }}" title="Edit {{ $plan->name }}" aria-label="Edit {{ $plan->name }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form class="d-inline" method="POST" action="{{ route('plans.destroy', $plan) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" @disabled($planUsed) title="{{ $planUsed ? 'Used plans must be made inactive instead of deleted' : 'Delete '.$plan->name }}" aria-label="Delete {{ $plan->name }}" data-confirm-title="Delete {{ $plan->name }}?" data-confirm="This unused plan will be permanently removed." data-confirm-icon="danger" data-confirm-button="Delete plan">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManagePlans ? 7 : 6 }}" class="text-center py-5 text-secondary">{{ $filtered ? 'No plans match these filters.' : 'Create the first access plan.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3">{{ $plans->links() }}</div>
    </div>

    <div class="col-xl-4">
        <div class="card metric-card">
            <div class="card-header"><h2 class="h5 mb-0">Create plan</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('plans.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">Plan name</label><input class="form-control" name="name" value="{{ old('name') }}" required placeholder="e.g. 2 Hours"></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Access type</label><select class="form-select" name="access_type"><option value="paid">Paid</option><option value="free">Free</option><option value="internal">Internal</option></select></div>
                        <div class="col-6"><label class="form-label">Price (₦)</label><input class="form-control" type="number" min="0" step="0.01" name="price_naira" value="{{ old('price_naira') }}" placeholder="e.g. 500" required><div class="form-text">Free and internal plans only may be ₦0.</div></div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Minutes</label><input class="form-control" type="number" min="1" name="duration_minutes"></div>
                        <div class="col-6"><label class="form-label">Data (MB)</label><input class="form-control" type="number" min="1" name="data_limit_mb"></div>
                        <div class="col-6"><label class="form-label">Download Kbps</label><input class="form-control" type="number" min="64" name="download_kbps"></div>
                        <div class="col-6"><label class="form-label">Upload Kbps</label><input class="form-control" type="number" min="64" name="upload_kbps"></div>
                        <div class="col-6"><label class="form-label">Devices</label><input class="form-control" type="number" min="1" max="20" name="simultaneous_use" value="1" required></div>
                        <div class="col-6"><label class="form-label">Validity days</label><input class="form-control" type="number" min="1" name="validity_days"></div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="validity-mode">Validity expiry</label>
                        <select class="form-select" id="validity-mode" name="validity_mode">
                            @foreach($validityModes as $mode)<option value="{{ $mode->value }}" @selected(old('validity_mode', 'midnight') === $mode->value)>{{ $mode->label() }}</option>@endforeach
                        </select>
                        <div class="form-text">Timezone: {{ $currentOrganization->timezone }}</div>
                    </div>
                    <button class="btn btn-hotfii w-100 mt-4">Create access plan</button>
                </form>
            </div>
        </div>
    </div>
</div>

@if($canManagePlans)
    @foreach($plans as $plan)
        @php
            $planUsed = $plan->voucher_batches_count
                + $plan->transactions_count
                + $plan->access_credentials_count
                + $plan->sessions_count > 0;
            $dataLimitMb = $plan->data_limit_bytes ? intdiv($plan->data_limit_bytes, 1024 * 1024) : null;
        @endphp
        <div class="modal fade" id="edit-plan-{{ $plan->id }}" tabindex="-1" aria-labelledby="edit-plan-label-{{ $plan->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="{{ route('plans.update', $plan) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="edit-plan-label-{{ $plan->id }}">Edit {{ $plan->name }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if($planUsed)
                                <div class="alert alert-info">This plan has already been issued. Its access type and technical limits are locked, but its name, future price, and active status can still be changed.</div>
                            @endif

                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Plan name</label><input class="form-control" name="name" value="{{ $plan->name }}" required></div>
                                <div class="col-md-3">
                                    <label class="form-label">Access type</label>
                                    <select class="form-select" name="access_type" @disabled($planUsed)>
                                        @foreach($types as $type)<option value="{{ $type }}" @selected($plan->access_type === $type)>{{ ucfirst($type) }}</option>@endforeach
                                    </select>
                                    @if($planUsed)<input type="hidden" name="access_type" value="{{ $plan->access_type }}">@endif
                                </div>
                                <div class="col-md-3"><label class="form-label">Price (₦)</label><input class="form-control" type="number" min="0" step="0.01" name="price_naira" value="{{ number_format($plan->price_kobo / 100, 2, '.', '') }}" required></div>

                                <div class="col-md-3"><label class="form-label">Minutes</label><input class="form-control" type="number" min="1" name="duration_minutes" value="{{ $plan->duration_minutes }}" @disabled($planUsed)>@if($planUsed)<input type="hidden" name="duration_minutes" value="{{ $plan->duration_minutes }}">@endif</div>
                                <div class="col-md-3"><label class="form-label">Data (MB)</label><input class="form-control" type="number" min="1" name="data_limit_mb" value="{{ $dataLimitMb }}" @disabled($planUsed)>@if($planUsed)<input type="hidden" name="data_limit_mb" value="{{ $dataLimitMb }}">@endif</div>
                                <div class="col-md-3"><label class="form-label">Download Kbps</label><input class="form-control" type="number" min="64" name="download_kbps" value="{{ $plan->download_kbps }}" @disabled($planUsed)>@if($planUsed)<input type="hidden" name="download_kbps" value="{{ $plan->download_kbps }}">@endif</div>
                                <div class="col-md-3"><label class="form-label">Upload Kbps</label><input class="form-control" type="number" min="64" name="upload_kbps" value="{{ $plan->upload_kbps }}" @disabled($planUsed)>@if($planUsed)<input type="hidden" name="upload_kbps" value="{{ $plan->upload_kbps }}">@endif</div>
                                <div class="col-md-3"><label class="form-label">Devices</label><input class="form-control" type="number" min="1" max="20" name="simultaneous_use" value="{{ $plan->simultaneous_use }}" required @disabled($planUsed)>@if($planUsed)<input type="hidden" name="simultaneous_use" value="{{ $plan->simultaneous_use }}">@endif</div>
                                <div class="col-md-3"><label class="form-label">Validity days</label><input class="form-control" type="number" min="1" max="65535" name="validity_days" value="{{ $plan->validity_days }}" @disabled($planUsed)>@if($planUsed)<input type="hidden" name="validity_days" value="{{ $plan->validity_days }}">@endif</div>
                                <div class="col-md-4">
                                    <label class="form-label">Validity expiry</label>
                                    <select class="form-select" name="validity_mode" @disabled($planUsed)>
                                        @foreach($validityModes as $mode)<option value="{{ $mode->value }}" @selected($plan->validity_mode->value === $mode->value)>{{ $mode->label() }}</option>@endforeach
                                    </select>
                                    @if($planUsed)<input type="hidden" name="validity_mode" value="{{ $plan->validity_mode->value }}">@endif
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="plan-active-{{ $plan->id }}" @checked($plan->is_active)>
                                        <label class="form-check-label" for="plan-active-{{ $plan->id }}">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-hotfii" data-confirm-title="Save changes to {{ $plan->name }}?" data-confirm="Future sales use the updated name, price, and status. Existing transaction amounts and voucher price snapshots are not rewritten." data-confirm-icon="question" data-confirm-button="Save changes">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
