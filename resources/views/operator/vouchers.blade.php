@extends('layouts.app')
@section('title', 'Vouchers')
@section('heading', 'Voucher Batches')
@section('subheading', 'Generate, assign, print, sell, and redeem hard-copy access')
@section('content')
<div class="row g-4">
    <div class="col-xl-8"><div class="card metric-card"><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Plan</th><th>Quantity</th><th>Retail value</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($batches as $batch)<tr><td class="fw-semibold">{{ $batch->reference }}</td><td>{{ $batch->accessPlan->name }}</td><td>{{ number_format($batch->quantity) }}</td><td>₦{{ number_format(($batch->retail_price_kobo * $batch->quantity) / 100, 0) }}</td><td><span class="badge text-bg-light border">{{ ucfirst($batch->status instanceof BackedEnum ? $batch->status->value : $batch->status) }}</span></td><td><a class="btn btn-sm btn-outline-success" href="{{ route('vouchers.print', $batch) }}"><i class="bi bi-printer me-1"></i>PDF</a></td></tr>@empty<tr><td colspan="6" class="text-center py-5 text-secondary">No voucher batches yet.</td></tr>@endforelse</tbody>
    </table></div></div></div><div class="mt-3">{{ $batches->links() }}</div></div>
    <div class="col-xl-4"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Generate batch</h2></div><div class="card-body">
        @if($plans->isEmpty())<div class="alert alert-warning">Create an active access plan first.</div>@else<form method="POST" action="{{ route('vouchers.store') }}">@csrf
            <div class="mb-3"><label class="form-label">Access plan</label><select class="form-select" name="access_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · ₦{{ number_format($plan->price_kobo / 100, 0) }}</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" min="1" max="5000" value="20" required></div>
            <div class="mb-3"><label class="form-label">Retail price per voucher (₦)</label><input type="number" class="form-control" name="retail_price_naira" min="0" step="0.01" placeholder="Use plan price"></div>
            <p class="small text-secondary"><i class="bi bi-info-circle me-1"></i>Validity begins on first successful activation. Default simultaneous use comes from the plan.</p>
            <button class="btn btn-hotfii w-100">Generate and print</button>
        </form>@endif
    </div></div></div>
</div>
@endsection