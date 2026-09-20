@extends('layouts.platform')
@section('title', 'Billing & invoices')
@section('heading', 'Billing &amp; invoices')
@section('subheading', 'What the platform earned, what the gateway already took, and what is still owed')
@section('content')

@php
    // The month table reads newest-first; a time axis has to read oldest-first.
    $series = array_reverse($months);
@endphp

<div class="row g-3 mb-4">
    @foreach([
        ['Outstanding on invoices', \App\Support\Naira::from($invoiceTotals['open']), 'warning'],
        ['Settled on invoices', \App\Support\Naira::from($invoiceTotals['paid']), 'success'],
        ['Invoices generated', number_format($invoiceTotals['count']), 'secondary'],
        ['Platform fee', number_format($pricing['fee_bps'] / 100, 2) . '% per sale', 'primary'],
    ] as [$label, $value, $tone])
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100"><div class="card-body">
                <div class="text-secondary small">{{ $label }}</div>
                <div class="fs-5 fw-bold text-{{ $tone === 'secondary' ? 'body' : $tone }}">{{ $value }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card metric-card mb-4">
    <div class="card-header border-0 pt-4 px-4">
        <span class="hf-chart-eyebrow">Last 12 months</span>
        <h2 class="h5 mb-0">Fees earned against fees collected</h2>
    </div>
    <div class="card-body pt-2 px-3 pb-3">
        @if(collect($months)->sum('fees') > 0)
            <div id="hf-fees-chart" class="hf-chart" role="img"
                 aria-label="Platform fees earned against platform fees collected at the gateway, for each of the last twelve months, in naira."
                 data-labels='@json(array_column($series, 'label'))'
                 data-accrued='@json(array_map(fn ($month) => \App\Support\Naira::value($month['fees']), $series))'
                 data-collected='@json(array_map(fn ($month) => \App\Support\Naira::value($month['collected']), $series))'></div>
        @else
            <div class="text-center text-secondary py-5"><i class="bi bi-percent fs-1 d-block mb-2 opacity-50"></i>No platform fees recorded yet.</div>
        @endif
    </div>
    {{-- The table view, and the only place the outstanding column is spelled out. --}}
    <div class="card-body p-0 border-top"><div class="table-responsive"><table class="table mb-0">
        <caption class="visually-hidden">Sales, fees earned, fees collected and fees outstanding by month</caption>
        <thead><tr><th>Month</th><th class="text-end">Billable sales</th><th class="text-end">Fees earned</th><th class="text-end">Collected at gateway</th><th class="text-end">Outstanding</th></tr></thead>
        <tbody>@foreach($months as $month)
            <tr class="{{ $month['key'] === $selected->format('Y-m') ? 'table-active' : '' }}">
                <th scope="row" class="fw-normal"><a class="text-decoration-none" href="{{ route('platform.billing.index', ['period' => $month['key']]) }}">{{ $month['label'] }}</a></th>
                <td class="text-end">{{ \App\Support\Naira::from($month['sales']) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($month['fees']) }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($month['collected']) }}</td>
                <td class="text-end {{ $month['outstanding'] > 0 ? 'text-warning fw-semibold' : 'text-secondary' }}">{{ \App\Support\Naira::from($month['outstanding']) }}</td>
            </tr>
        @endforeach</tbody>
    </table></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card metric-card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 mb-0">Where {{ $selected->format('F Y') }} came from</h2>
                <form class="d-flex gap-2" method="GET" action="{{ route('platform.billing.index') }}">
                    {{-- Carry the invoice filters across, so changing the month
                         does not silently reset the list below. --}}
                    @if($filters['invoice_status'])<input type="hidden" name="invoice_status" value="{{ $filters['invoice_status'] }}">@endif
                    @if($filters['invoice_period'])<input type="hidden" name="invoice_period" value="{{ $filters['invoice_period'] }}">@endif
                    <input class="form-control form-control-sm" type="month" name="period" value="{{ $selected->format('Y-m') }}" aria-label="Billing month">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Show</button>
                </form>
            </div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Organization</th><th class="text-end">Billable sales</th><th class="text-end">Fee earned</th><th class="text-end">Collected</th></tr></thead>
                <tbody>@forelse($breakdown as $row)
                    <tr>
                        <td>@if($row->organization)<a class="text-decoration-none" href="{{ route('platform.organizations.show', $row->organization) }}">{{ $row->organization->name }}</a>@else<span class="text-secondary">Deleted organization #{{ $row->organization_id }}</span>@endif</td>
                        <td class="text-end">{{ \App\Support\Naira::from((int) $row->sales_kobo) }}</td>
                        <td class="text-end">{{ \App\Support\Naira::from((int) $row->fee_kobo) }}</td>
                        <td class="text-end">{{ \App\Support\Naira::from((int) $row->collected_kobo) }}</td>
                    </tr>
                @empty<tr><td colspan="4" class="text-center py-5 text-secondary">No fees accrued in {{ $selected->format('F Y') }}.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>
    </div>
    <div class="col-xl-5">
        {{-- Read straight from configuration, so this card is the answer to "what
             are we actually charging" without opening a server. --}}
        <div class="card metric-card h-100">
            <div class="card-header"><h2 class="h5 mb-0">Current pricing</h2></div>
            <div class="list-group list-group-flush">
                @foreach([
                    'Running fee per paid sale' => number_format($pricing['fee_bps'] / 100, 2) . '%',
                    'Monthly charge through the included band' => \App\Support\Naira::from($pricing['standard_minimum_kobo']),
                    'Sales included in that charge' => 'Up to ' . \App\Support\Naira::from($pricing['minimum_included_sales_kobo']),
                    'Sales above the included band' => number_format($pricing['fee_bps'] / 100, 2) . '% of the excess',
                    'Trial sales cap' => \App\Support\Naira::from($pricing['trial_sales_cap_kobo']),
                    'Trial length' => $pricing['trial_days'] . ' days',
                    'Grace period' => $pricing['grace_days'] . ' days',
                ] as $label => $value)
                    <div class="list-group-item d-flex justify-content-between gap-3"><span>{{ $label }}</span><strong class="text-end">{{ $value }}</strong></div>
                @endforeach
            </div>
            <div class="card-body p-0 border-top"><div class="table-responsive"><table class="table table-sm mb-0">
                <caption class="visually-hidden">Internal (non-selling) plan prices</caption>
                <thead><tr><th>Internal plan</th><th class="text-end">Monthly</th><th class="text-end">Sites</th><th class="text-end">Identities</th></tr></thead>
                <tbody>@foreach($internalPlans as $code => $plan)
                    <tr>
                        <td class="small">{{ str_replace('_', ' ', ucfirst($code)) }}</td>
                        <td class="text-end">{{ \App\Support\Naira::from((int) $plan['price_kobo']) }}</td>
                        <td class="text-end">{{ $plan['sites'] }}</td>
                        <td class="text-end">{{ number_format($plan['active_identities']) }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div></div>
            <div class="card-footer small text-secondary"><i class="bi bi-lock me-1"></i>Read from <code>config/hotfii.php</code>. Prices change by editing <code>.env</code> on the server and rebuilding — not from this page.</div>
        </div>
    </div>
</div>

<div class="card metric-card">
    <x-filter-bar :action="route('platform.billing.index')" :active="$invoicesFiltered" title="Invoices">
        {{-- And back the other way: filtering invoices keeps the month the
             breakdown above is showing. --}}
        @if($filters['period'])<input type="hidden" name="period" value="{{ $filters['period'] }}">@endif
        <div class="col-md-3"><select class="form-select form-select-sm" name="invoice_status">
            <option value="">Any status</option>
            @foreach($invoiceStatuses as $value)<option value="{{ $value }}" @selected($filters['invoice_status'] === $value)>{{ ucfirst($value) }}</option>@endforeach
        </select></div>
        <div class="col-md-3"><input class="form-control form-control-sm" type="month" name="invoice_period" value="{{ $filters['invoice_period'] }}" aria-label="Invoice month"></div>
    </x-filter-bar>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Number</th><th>Organization</th><th>Period</th><th class="text-end">Subtotal</th><th class="text-end">Total</th><th>Status</th><th>Due</th><th>Paid</th><th class="text-end">Record payment</th></tr></thead>
        <tbody>@forelse($invoices as $invoice)
            <tr>
                <td><code class="small">{{ $invoice->number }}</code></td>
                <td>@if($invoice->organization)<a class="text-decoration-none" href="{{ route('platform.organizations.show', $invoice->organization) }}">{{ $invoice->organization->name }}</a>@else<span class="text-secondary">Deleted organization #{{ $invoice->organization_id }}</span>@endif</td>
                <td class="small">{{ $invoice->billing_period->format('M Y') }}</td>
                <td class="text-end">{{ \App\Support\Naira::from($invoice->subtotal_kobo) }}</td>
                <td class="text-end fw-semibold">{{ \App\Support\Naira::from($invoice->total_kobo) }}</td>
                <td>
                    <span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'open' ? 'warning' : 'secondary') }}">{{ ucfirst($invoice->status) }}</span>
                    @if($invoice->isOverdue())<span class="badge text-bg-danger ms-1">Overdue</span>@endif
                </td>
                <td class="small text-secondary">{{ $invoice->due_at?->format('j M Y') ?? '—' }}</td>
                <td class="small text-secondary">
                    {{ $invoice->paid_at?->format('j M Y') ?? '—' }}
                    @if($invoice->isPaid() && $invoice->payment_method)
                        <div class="text-body-tertiary">{{ $invoice->payment_method === 'manual' ? 'Transfer' : 'Card' }} · <code>{{ $invoice->payment_reference ?? '—' }}</code></div>
                    @endif
                </td>
                <td class="text-end">
                    @if($invoice->isPaid())
                        <span class="small text-secondary">Settled</span>
                    @else
                        {{-- A transfer that already landed in the bank, being
                             written down. The reference and reason are required
                             because this is the one place money can be declared
                             received without a gateway proving it. --}}
                        <form method="POST" action="{{ route('platform.invoices.pay', $invoice) }}" class="row g-1 justify-content-end flex-nowrap">
                            @csrf
                            @method('PATCH')
                            <div class="col-auto"><input class="form-control form-control-sm" name="reference" required minlength="3" maxlength="120" placeholder="Bank reference" aria-label="Bank reference for {{ $invoice->number }}" style="width:9rem"></div>
                            <div class="col-auto"><input class="form-control form-control-sm" name="reason" required minlength="10" maxlength="2000" placeholder="How it was confirmed" aria-label="Reason for marking {{ $invoice->number }} paid" style="width:11rem"></div>
                            <div class="col-auto"><button class="btn btn-sm btn-outline-success" type="submit"
                                data-confirm="Mark {{ $invoice->number }} ({{ \App\Support\Naira::from($invoice->total_kobo) }}) paid? This lifts any billing suspension on the account."
                                data-confirm-title="Record a payment"
                                data-confirm-icon="bi-cash-coin"
                                data-confirm-button="Mark paid"><i class="bi bi-check2"></i></button></div>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center py-5 text-secondary">{{ $invoicesFiltered ? 'No invoices match these filters.' : 'No invoices have been generated yet. GenerateMonthlyInvoices writes them on the first of each month.' }}</td></tr>
        @endforelse</tbody>
    </table></div></div>
    <div class="card-footer small text-secondary"><i class="bi bi-info-circle me-1"></i>Amounts are computed from the fee ledger and cannot be edited here. Recording a payment only writes down a transfer that already arrived — it is audited with the reference and reason, and lifts a billing suspension once nothing else is overdue.</div>
</div>
<div class="mt-3">{{ $invoices->links() }}</div>
@endsection

@push('scripts')
    @vite('resources/js/platform.js')
@endpush
