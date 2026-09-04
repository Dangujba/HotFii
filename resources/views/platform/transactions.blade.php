@extends('layouts.platform')
@section('title', 'Transactions')
@section('heading', 'Transactions')
@section('subheading', 'Every payment taken on the deployment, searchable by reference')
@section('content')

<div class="row g-3 mb-4">
    @foreach([
        ['Payments in this view', number_format($summary['count'])],
        ['Gross value', \App\Support\Naira::from($summary['gross'])],
        ['Platform fees', \App\Support\Naira::from($summary['fees'])],
    ] as [$label, $value])
        <div class="col-sm-4">
            <div class="card metric-card h-100"><div class="card-body">
                <div class="text-secondary small">{{ $label }}</div>
                <div class="fs-5 fw-bold">{{ $value }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card metric-card">
    <x-filter-bar :action="route('platform.transactions.index')" :active="$filtered">
        <div class="col-md-3"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Payment reference"></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="organization">
            <option value="">All organizations</option>
            @foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((int) $filters['organization'] === $organization->id)>{{ $organization->name }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" name="status">
            <option value="">Any status</option>
            @foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="date" name="from" value="{{ $filters['from'] }}" aria-label="From date"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="date" name="to" value="{{ $filters['to'] }}" aria-label="To date"></div>
    </x-filter-bar>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Organization</th><th>Plan</th><th>Channel</th><th class="text-end">Amount</th><th class="text-end">Platform fee</th><th>Status</th><th>Paid</th></tr></thead>
        <tbody>@forelse($transactions as $transaction)
            <tr>
                <td><code class="small">{{ $transaction->reference }}</code><button type="button" class="btn btn-sm btn-link p-0 ms-1 text-decoration-none" data-copy-text="{{ $transaction->reference }}" aria-label="Copy reference"><i class="bi bi-clipboard"></i></button></td>
                <td>@if($transaction->organization)<a class="text-decoration-none" href="{{ route('platform.organizations.show', $transaction->organization) }}">{{ $transaction->organization->name }}</a>@else<span class="text-secondary">Deleted organization #{{ $transaction->organization_id }}</span>@endif</td>
                <td class="small">{{ $transaction->accessPlan?->name ?? '—' }}</td>
                <td class="small">{{ ucfirst($transaction->channel) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->gross_amount_kobo) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($transaction->platform_fee_kobo) }}</td>
                <td><span class="badge text-bg-{{ $transaction->status->value === 'successful' ? 'success' : ($transaction->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($transaction->status->value) }}</span></td>
                <td class="small text-secondary">{{ $transaction->paid_at?->format('j M Y, H:i') ?? $transaction->created_at->format('j M Y, H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center py-5 text-secondary">{{ $filtered ? 'No payments match these filters.' : 'No payments have been taken yet.' }}</td></tr>
        @endforelse</tbody>
    </table></div></div>
</div>
<div class="mt-3">{{ $transactions->links() }}</div>
@endsection
