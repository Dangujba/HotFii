@extends('layouts.app')
@section('title', 'Vouchers')
@section('heading', 'Voucher Batches')
@section('subheading', 'Generate, assign, print, sell, and redeem hard-copy access')
@section('content')
<div class="row g-4">
    <div class="col-xl-8"><div class="card metric-card">
        <x-filter-bar :action="route('vouchers.index')" :active="$filtered">
            <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Batch reference"></div>
            <div class="col-md-3"><select class="form-select form-select-sm" name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select form-select-sm" name="plan"><option value="">All plans</option>@foreach($filterPlans as $option)<option value="{{ $option->id }}" @selected($filters['plan'] === $option->id)>{{ $option->name }}</option>@endforeach</select></div>
        </x-filter-bar>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Plan</th><th>Quantity</th><th>Retail value</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($batches as $batch)<tr><td class="fw-semibold">{{ $batch->reference }}</td><td>{{ $batch->accessPlan->name }}</td><td>{{ number_format($batch->quantity) }}</td><td>₦{{ number_format(($batch->retail_price_kobo * $batch->quantity) / 100, 0) }}</td><td><span class="badge text-bg-light border">{{ ucfirst($batch->status instanceof BackedEnum ? $batch->status->value : $batch->status) }}</span></td><td><a class="btn btn-sm btn-hotfii" href="{{ route('vouchers.print', $batch) }}" download><i class="bi bi-printer me-1"></i>PDF</a></td></tr>@empty<tr><td colspan="6" class="text-center py-5 text-secondary">{{ $filtered ? 'No batches match these filters.' : 'No voucher batches yet.' }}</td></tr>@endforelse</tbody>
    </table></div></div></div><div class="mt-3">{{ $batches->links() }}</div></div>
    <div class="col-xl-4"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Generate batch</h2></div><div class="card-body">
        @if($plans->isEmpty())<div class="alert alert-warning">Create an active access plan first.</div>@else<form method="POST" action="{{ route('vouchers.store') }}">@csrf
            <div class="mb-3"><label class="form-label">Access plan</label><select class="form-select" name="access_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · ₦{{ number_format($plan->price_kobo / 100, 0) }}</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" min="1" max="5000" value="20" required></div>
            <div class="mb-3"><label class="form-label">Retail price per voucher (₦)</label><input type="number" class="form-control" name="retail_price_naira" min="0" step="0.01" placeholder="Use plan price"></div>
            <div class="row g-2 mb-3">
                <div class="col-7"><label class="form-label">Pin characters</label><select class="form-select" name="pin_format">@foreach($pinFormats as $format)<option value="{{ $format->value }}" @selected($format->value === 'numbers')>{{ $format->label() }}</option>@endforeach</select></div>
                <div class="col-5"><label class="form-label">Grouping</label><select class="form-select" name="dashed_pin"><option value="1" selected>Add dashes</option><option value="0">No dashes</option></select></div>
            </div>
            <p class="small text-secondary"><i class="bi bi-info-circle me-1"></i>Pins are 12 characters, printed two per row. Validity begins on first successful activation. Default simultaneous use comes from the plan.</p>
            <button class="btn btn-hotfii w-100" data-confirm-title="Generate this voucher batch?" data-confirm="Codes are minted straight away and cannot be un-minted. Print them before handing any out." data-confirm-icon="question" data-confirm-button="Generate batch">Generate and print</button>
        </form>@endif
    </div></div></div>
</div>
{{-- The PDF is an attachment, so pulling it through a hidden frame keeps the
     page navigation intact and lets the loader and submit spinner finish. --}}
@if(session('download_batch'))<iframe src="{{ route('vouchers.print', session('download_batch')) }}" style="display:none" width="0" height="0" title="Voucher PDF download"></iframe>@endif
@endsection