@extends('layouts.app')
@section('title', 'Sales')
@section('heading', 'Sales & Agents')
@section('subheading', 'Direct payments and voucher activation activity')
@section('content')
<div class="row g-3 mb-4">@foreach([['Online sales','₦'.number_format($totals['online'] / 100, 0),'credit-card','success'],['Voucher activations',number_format($totals['voucher']),'ticket-perforated','warning'],['Direct cash sales','₦'.number_format($totals['cash'] / 100, 0),'cash','primary']] as [$label,$value,$icon,$color])<div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="fs-3 fw-bold">{{ $value }}</div><i class="bi bi-{{ $icon }} text-{{ $color }}"></i></div></div></div>@endforeach</div>
@if(session('issued_credential'))<div class="alert alert-success"><strong>Customer access credentials:</strong> <code>{{ session('issued_credential.username') }}</code> / <code>{{ session('issued_credential.password') }}</code>. Give these to the customer now.</div>@endif
<div class="card metric-card mb-4"><div class="card-header"><h2 class="h5 mb-0">Record cash-plan activation</h2></div><div class="card-body">
@if($plans->isEmpty() || $devices->isEmpty())<div class="alert alert-warning mb-0">You need an active paid plan and an online, fully tested router before recording live cash access.</div>@else
<form class="row g-3 align-items-end" method="POST" action="{{ route('sales.cash.store') }}">@csrf
<div class="col-md-3"><label class="form-label">Plan</label><select class="form-select" name="access_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · ₦{{ number_format($plan->price_kobo/100,0) }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Router</label><select class="form-select" name="network_device_id">@foreach($devices as $device)<option value="{{ $device->id }}">{{ $device->name }}</option>@endforeach</select></div>
<div class="col-md-2"><label class="form-label">Customer name</label><input class="form-control" name="customer_name"></div><div class="col-md-2"><label class="form-label">Phone</label><input class="form-control" name="phone"></div><div class="col-md-2"><button class="btn btn-hotfii w-100">Record & activate</button></div>
</form>@endif
</div></div>
<div class="row g-4">
<div class="col-xl-7"><div class="card metric-card">
<x-filter-bar :action="route('sales.index')" :active="$filtered" title="Direct online & cash transactions">
    <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Reference or customer"></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="channel"><option value="">All channels</option>@foreach($channels as $channel)<option value="{{ $channel }}" @selected($filters['channel'] === $channel)>{{ ucfirst($channel) }}</option>@endforeach</select></div>
    <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">From</span><input class="form-control" type="date" name="from" value="{{ $filters['from'] }}"></div></div>
    <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text">To</span><input class="form-control" type="date" name="to" value="{{ $filters['to'] }}"></div></div>
</x-filter-bar>
<div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Reference</th><th>Customer</th><th>Plan</th><th>Channel</th><th>Amount</th><th>Status</th></tr></thead><tbody>@forelse($transactions as $transaction)<tr><td><code>{{ $transaction->reference }}</code></td><td>{{ $transaction->customer?->email ?: 'Walk-in' }}</td><td>{{ $transaction->accessPlan?->name }}</td><td>{{ ucfirst($transaction->channel) }}</td><td>₦{{ number_format($transaction->gross_amount_kobo / 100, 0) }}</td><td><span class="badge text-bg-{{ $transaction->status->value === 'successful' ? 'success' : ($transaction->status->value === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($transaction->status->value) }}</span></td></tr>@empty<tr><td colspan="6" class="text-center py-5 text-secondary">{{ $filtered ? 'No transactions match these filters.' : 'No transactions yet.' }}</td></tr>@endforelse</tbody></table></div></div></div><div class="mt-3">{{ $transactions->links() }}</div></div>
<div class="col-xl-5"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Voucher activations</h2></div><div class="list-group list-group-flush">@forelse($voucherSales as $voucher)<div class="list-group-item d-flex justify-content-between"><div><strong>Voucher •••• {{ $voucher->code_last_four }}</strong><div class="small text-secondary">{{ $voucher->batch->accessPlan->name }} · {{ $voucher->sold_at->diffForHumans() }}</div></div><strong>₦{{ number_format($voucher->price_snapshot_kobo / 100, 0) }}</strong></div>@empty<div class="p-5 text-center text-secondary">No activated vouchers.</div>@endforelse</div></div><div class="mt-3">{{ $voucherSales->links() }}</div></div>
</div>
@endsection
