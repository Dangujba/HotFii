@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Settings')
@section('subheading', 'Organization, captive portal, payment activation, security, and audit history')
@section('content')
<div class="row g-4"><div class="col-xl-7">
<div class="card metric-card mb-4"><div class="card-header"><h2 class="h5 mb-0">Organization & captive portal</h2></div><div class="card-body"><form method="POST" action="{{ route('settings.update') }}">@csrf @method('PATCH')
<div class="row g-3"><div class="col-md-6"><label class="form-label">Organization name</label><input class="form-control" name="name" value="{{ old('name', $currentOrganization->name) }}" required></div><div class="col-md-6"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="{{ old('timezone', $currentOrganization->timezone) }}" required></div><div class="col-md-6"><label class="form-label">Portal display name</label><input class="form-control" name="portal_name" value="{{ old('portal_name', $currentOrganization->branding['portal_name'] ?? $currentOrganization->name) }}"></div><div class="col-md-6"><label class="form-label">Brand colour</label><input class="form-control form-control-color w-100" type="color" name="portal_primary_color" value="{{ old('portal_primary_color', $currentOrganization->branding['primary_color'] ?? '#f4610a') }}"></div></div>
<button class="btn btn-hotfii mt-4">Save settings</button></form></div></div>

@if($currentOrganization->sellsAccess())
<div class="card metric-card"><div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0">Live payment profile</h2><span class="badge text-bg-{{ ($paymentProfile?->status) === 'approved' ? 'success' : (($paymentProfile?->status) === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($paymentProfile?->status ?? 'not submitted') }}</span></div><div class="card-body">
@if($paymentProfile?->status === 'approved')
<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><strong>Live payments are on.</strong> Settling to {{ $paymentProfile->resolved_account_name ?: $paymentProfile->account_name }} at {{ $paymentProfile->bank_name }}.@if($paymentProfile->wasAutoApproved()) Approved automatically {{ $paymentProfile->auto_approved_at->diffForHumans() }}.@endif</div>
@elseif($paymentProfile?->review_notes)<div class="alert alert-{{ $paymentProfile->status === 'rejected' ? 'danger' : 'info' }}"><strong>Review note:</strong> {{ $paymentProfile->review_notes }}</div>@endif
<p class="text-secondary">Only payment information is checked. Router, plan, RADIUS, voucher, and Paystack test-mode work remains available in sandbox.</p>
@if($banks !== [])
<div class="alert alert-info py-2 small mb-3"><i class="bi bi-lightning-charge-fill me-1"></i>Approval is automatic: HotFii asks your bank to confirm the account, then opens your settlement subaccount straight away.</div>
@endif
<form method="POST" action="{{ route('settings.payment-profile') }}">@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="pp-business-name">Registered business name</label><input id="pp-business-name" class="form-control" name="business_name" value="{{ old('business_name', $paymentProfile?->business_name ?? $currentOrganization->name) }}" required></div>
    <div class="col-md-6"><label class="form-label" for="pp-contact-name">Contact name</label><input id="pp-contact-name" class="form-control" name="contact_name" value="{{ old('contact_name', $paymentProfile?->contact_name) }}" required></div>
    <div class="col-md-6"><label class="form-label" for="pp-contact-phone">Contact phone</label><input id="pp-contact-phone" class="form-control" name="contact_phone" value="{{ old('contact_phone', $paymentProfile?->contact_phone) }}" required></div>
    <div class="col-md-6">
        <label class="form-label" for="pp-bank">Settlement bank</label>
        @if($banks !== [])
            <select id="pp-bank" class="form-select" name="bank_code" data-search-placeholder="Search banks" required>
                <option value="">Choose your bank</option>
                @foreach($banks as $bank)
                    <option value="{{ $bank['code'] }}" @selected(old('bank_code', $paymentProfile?->bank_code) === $bank['code'])>{{ $bank['name'] }}</option>
                @endforeach
            </select>
        @else
            <input id="pp-bank" class="form-control" name="bank_name" value="{{ old('bank_name', $paymentProfile?->bank_name) }}" required>
            <div class="form-text">The bank list is unavailable, so this profile goes to manual review.</div>
        @endif
    </div>
    <div class="col-md-6"><label class="form-label" for="pp-account-name">Account name</label><input id="pp-account-name" class="form-control" name="account_name" value="{{ old('account_name', $paymentProfile?->account_name) }}" required><div class="form-text">Must match the name your bank holds for the account.</div></div>
    <div class="col-md-6"><label class="form-label" for="pp-account-number">Account number</label><input id="pp-account-number" class="form-control" name="account_number" inputmode="numeric" pattern="[0-9]{10,16}" required placeholder="{{ $paymentProfile ? 'Re-enter to resubmit' : '' }}"></div>
    <div class="col-md-6"><label class="form-label" for="pp-identity-type">Identity type</label><select id="pp-identity-type" class="form-select" name="identity_type"><option value="nin" @selected(old('identity_type', $paymentProfile?->identity_type) === 'nin')>NIN</option><option value="bvn" @selected(old('identity_type', $paymentProfile?->identity_type) === 'bvn')>BVN</option><option value="cac" @selected(old('identity_type', $paymentProfile?->identity_type) === 'cac')>CAC</option></select></div>
    <div class="col-md-6"><label class="form-label" for="pp-identity-number">Identity number</label><input id="pp-identity-number" class="form-control" name="identity_number" required placeholder="{{ $paymentProfile ? 'Re-enter to resubmit' : '' }}"></div>
</div>
<button class="btn btn-hotfii mt-4" data-confirm-title="Switch on live payments?" data-confirm="HotFii will ask your bank to confirm this account and then start settling real customer payments into it. Check the account number before continuing." data-confirm-icon="warning" data-confirm-button="Confirm and go live">{{ $banks !== [] ? 'Verify and enable live payments' : 'Submit for payment review' }}</button></form></div></div>
@endif
</div>
<div class="col-xl-5"><div class="card metric-card"><div class="card-header"><h2 class="h5 mb-0">Audit history</h2></div><div class="list-group list-group-flush">@forelse($auditLogs as $log)<div class="list-group-item"><strong>{{ str_replace('.', ' · ', $log->action) }}</strong><div class="small text-secondary">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</div>@if($log->reason)<div class="small mt-1">{{ $log->reason }}</div>@endif</div>@empty<div class="p-5 text-center text-secondary">No audited changes yet.</div>@endforelse</div></div></div></div>
@endsection