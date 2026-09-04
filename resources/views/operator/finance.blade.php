@extends('layouts.app')
@section('title', 'Finance')
@section('heading', 'Finance')
@section('subheading', 'Transparent sales, HotFii fees, invoices, and subscription status')
@section('content')
<div class="row g-3 mb-4"><div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary">Billable sales this month</div><div class="fs-3 fw-bold">₦{{ number_format($current['sales'] / 100, 0) }}</div></div></div></div><div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary">HotFii fees accrued/collected</div><div class="fs-3 fw-bold">₦{{ number_format($current['fees'] / 100, 0) }}</div></div></div></div><div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary">Current plan</div><div class="fs-5 fw-bold">{{ str_replace('_', ' ', ucfirst($currentOrganization->billing_plan->value)) }}</div><div class="small text-secondary">{{ $subscription?->status ?? 'No separate subscription record' }}</div></div></div></div></div>
<div class="row g-4"><div class="col-xl-8"><div class="card metric-card">
<x-filter-bar :action="route('finance.index')" :active="$ledgerFiltered" title="HotFii charge ledger">
    {{-- The invoice list filters through its own form, so carry its value across. --}}
    <input type="hidden" name="invoice_status" value="{{ $filters['invoice_status'] }}">
    <div class="col-md-4"><select class="form-select form-select-sm" name="status"><option value="">Any status</option>@foreach($entryStatuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-4"><div class="input-group input-group-sm"><span class="input-group-text">Period</span><input class="form-control" type="month" name="period" value="{{ $filters['period'] }}"></div></div>
</x-filter-bar>
<div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Period</th><th>Source</th><th>Billable sale</th><th>Fee</th><th>Status</th></tr></thead><tbody>@forelse($entries as $entry)<tr><td>{{ $entry->billing_period->format('M Y') }}</td><td>{{ ucfirst($entry->source_type) }} #{{ $entry->source_id }}</td><td>₦{{ number_format($entry->billable_sales_kobo / 100, 0) }}</td><td>₦{{ number_format($entry->fee_amount_kobo / 100, 0) }}</td><td>{{ ucfirst($entry->status) }}</td></tr>@empty<tr><td colspan="5" class="text-center py-5 text-secondary">{{ $ledgerFiltered ? 'No ledger entries match these filters.' : 'Fees appear after paid activations.' }}</td></tr>@endforelse</tbody></table></div></div></div><div class="mt-3">{{ $entries->links() }}</div></div>
<div class="col-xl-4"><div class="card metric-card">
<x-filter-bar :action="route('finance.index')" :active="$invoicesFiltered" title="Invoices">
    {{-- Likewise, keep the ledger's filters when this form submits. --}}
    <input type="hidden" name="status" value="{{ $filters['status'] }}">
    <input type="hidden" name="period" value="{{ $filters['period'] }}">
    <div class="col"><select class="form-select form-select-sm" name="invoice_status"><option value="">Any status</option>@foreach($invoiceStatuses as $status)<option value="{{ $status }}" @selected($filters['invoice_status'] === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
</x-filter-bar>
<div class="list-group list-group-flush">@forelse($invoices as $invoice)<div class="list-group-item"><div class="d-flex justify-content-between"><strong>{{ $invoice->number }}</strong><span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($invoice->status) }}</span></div><div class="d-flex justify-content-between mt-2"><span>{{ $invoice->billing_period->format('M Y') }}</span><strong>₦{{ number_format($invoice->total_kobo / 100, 0) }}</strong></div><div class="small text-secondary">Due {{ $invoice->due_at?->format('d M Y') }}</div></div>@empty<div class="p-5 text-center text-secondary">{{ $invoicesFiltered ? 'No invoices with that status.' : 'No invoices.' }}</div>@endforelse</div></div><div class="mt-3">{{ $invoices->links() }}</div></div></div>
@endsection