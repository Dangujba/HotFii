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
            <div class="col-md-3"><select class="form-select form-select-sm" name="router"><option value="">All router coverage</option>@foreach($routers as $router)<option value="{{ $router->id }}" @selected($filters['router'] === $router->id)>{{ $router->name }}</option>@endforeach</select></div>
        </x-filter-bar>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Reference</th><th>Coverage</th><th>Plan</th><th>Quantity</th><th>Retail value</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($batches as $batch)<tr><td class="fw-semibold">{{ $batch->reference }}</td><td>@if($batch->networkDevice)<strong>{{ $batch->networkDevice->name }}</strong><div class="small text-secondary">{{ $batch->networkDevice->nas_identifier }}</div>@else<span class="badge text-bg-primary">All routers</span>@endif</td><td>{{ $batch->accessPlan->name }}</td><td>{{ number_format($batch->quantity) }}</td><td>₦{{ number_format(($batch->retail_price_kobo * $batch->quantity) / 100, 0) }}</td><td><span class="badge text-bg-light border">{{ ucfirst($batch->status instanceof BackedEnum ? $batch->status->value : $batch->status) }}</span></td><td>
@php
    $pdfParts = max(1, (int) ceil($batch->quantity / 100));
@endphp

@if($pdfParts === 1)
    <a
        class="btn btn-sm btn-hotfii"
        href="{{ route('vouchers.print', $batch) }}"
    >
        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
    </a>
@else
    <div class="dropdown">
        <button
            class="btn btn-sm btn-hotfii dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            <i class="bi bi-file-earmark-pdf me-1"></i>
            PDFs ({{ $pdfParts }})
        </button>

        <ul class="dropdown-menu dropdown-menu-end">
            @for($part = 1; $part <= $pdfParts; $part++)
                @php
                    $from = (($part - 1) * 100) + 1;
                    $to = min($part * 100, $batch->quantity);
                @endphp

                <li>
                    <a
                        class="dropdown-item"
                        href="{{ route('vouchers.print', ['batch' => $batch, 'part' => $part]) }}"
                    >
                        Part {{ $part }}
                        <span class="text-secondary">
                            · vouchers {{ $from }}–{{ $to }}
                        </span>
                    </a>
                </li>
            @endfor
        </ul>
    </div>
@endif
</td></tr>@empty<tr><td colspan="7" class="text-center py-5 text-secondary">{{ $filtered ? 'No batches match these filters.' : 'No voucher batches yet.' }}</td></tr>@endforelse</tbody>
    </table></div></div></div><div class="mt-3">{{ $batches->links() }}</div></div>
    <div class="col-xl-4"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Generate batch</h2></div><div class="card-body">
        @if($routers->isEmpty())
            <div class="alert alert-warning">Add a router before generating vouchers.</div>
        @elseif($plans->isEmpty())
            <div class="alert alert-warning">Create an active access plan first.</div>
        @else
        <form method="POST" action="{{ route('vouchers.store') }}">@csrf
            <div class="mb-3">
                <label class="form-label">Router / Coverage</label>

                <select
                    class="form-select"
                    name="network_device_id"
                    required
                >
                    <option value="">Choose coverage</option>

                    <option
                        value="all"
                        @selected(old('network_device_id') === 'all')
                    >
                        All routers in this organization
                    </option>

                    @foreach($routers as $router)
                        <option
                            value="{{ $router->id }}"
                            @selected(
                                (string) old('network_device_id')
                                === (string) $router->id
                            )
                        >
                            {{ $router->name }}
                            · {{ $router->vendor->label() }}
                            · {{ $router->nas_identifier }}
                        </option>
                    @endforeach
                </select>

                <div class="form-text">
                    A specific router restricts the vouchers to that router.
                    All routers allows them on any router in this organization.
                </div>
            </div>
            <div class="mb-3"><label class="form-label">Access plan</label><select class="form-select" name="access_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · ₦{{ number_format($plan->price_kobo / 100, 0) }}</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" min="1" max="5000" value="20" required></div>
            <div class="mb-3"><label class="form-label">Retail price per voucher (₦)</label><input type="number" class="form-control" name="retail_price_naira" min="1" step="0.01" placeholder="Use plan price"></div>
            <div class="row g-2 mb-3">
                <div class="col-12"><label class="form-label" for="pin-format">PIN characters</label><select class="form-select" id="pin-format" name="pin_format">@foreach($pinFormats as $format)<option value="{{ $format->value }}" @selected(old('pin_format', 'numbers') === $format->value)>{{ $format->label() }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label" for="pin-length">PIN length</label><select class="form-select" id="pin-length" name="pin_length">@foreach($pinLengths as $length)<option value="{{ $length }}" @selected((int) old('pin_length', 12) === $length)>{{ $length }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Grouping</label><select class="form-select" name="dashed_pin"><option value="1" @selected((string) old('dashed_pin', '1') === '1')>Add dashes</option><option value="0" @selected((string) old('dashed_pin', '1') === '0')>No dashes</option></select></div>
            </div>
            <button class="btn btn-hotfii w-100" data-confirm-title="Generate this voucher batch?" data-confirm="Codes are minted straight away and cannot be un-minted. Print them before handing any out." data-confirm-icon="question" data-confirm-button="Generate batch">Generate and print</button>
        </form>@endif
    </div></div></div>
</div>

@endsection
