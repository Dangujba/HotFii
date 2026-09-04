@extends('layouts.platform')
@section('title', 'Payment reviews')
@section('heading', 'Payment reviews')
@section('subheading', 'Profiles Paystack could not verify on its own, and every decision already taken')
@section('content')

{{-- Tabs, not a dropdown: the queue is the page's reason to exist, and the
     counts have to be visible without opening a select. --}}
<ul class="nav nav-pills mb-4">
    @foreach($statuses as $value)
        <li class="nav-item"><a class="nav-link {{ $status === $value ? 'active' : '' }}" href="{{ route('platform.reviews.index', ['status' => $value]) }}">
            {{ ucfirst($value) }}
            <span class="badge rounded-pill text-bg-{{ $status === $value ? 'light' : 'secondary' }} ms-1">{{ $counts[$value] }}</span>
        </a></li>
    @endforeach
</ul>

<div class="card metric-card">
    <x-filter-bar :action="route('platform.reviews.index')" :active="$filtered">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Organization name"></div>
    </x-filter-bar>
    <div class="card-body p-0">@forelse($organizations as $organization)
        <div class="p-4 border-bottom">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="h6 mb-1"><a class="text-decoration-none" href="{{ route('platform.organizations.show', $organization) }}">{{ $organization->name }}</a></h3>
                    <span class="text-secondary small">
                        {{ ucfirst($organization->mode->value) }} · submitted {{ $organization->paymentProfile?->submitted_at?->diffForHumans() ?? 'unknown' }}
                        @if($organization->paymentProfile?->reviewed_at)
                            · decided {{ $organization->paymentProfile->reviewed_at->diffForHumans() }} by {{ $organization->paymentProfile->reviewer?->name ?? 'System' }}
                        @endif
                    </span>
                </div>
                <span class="badge text-bg-{{ $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">{{ $status === 'submitted' ? 'Review' : ucfirst($status) }}</span>
            </div>

            @if($organization->paymentProfile)
                {{-- The reviewer's whole job is checking these three against the
                     bank, so the account number is shown in full here — the one
                     place in the console that does. --}}
                <div class="row g-2 small mt-3">
                    <div class="col-md-4"><strong>Contact</strong>
                        <div>{{ $organization->paymentProfile->contact_name }}<br>{{ $organization->paymentProfile->contact_phone }}</div>
                    </div>
                    <div class="col-md-4"><strong>Settlement</strong>
                        <div>
                            {{ $organization->paymentProfile->bank_name }}@if($organization->paymentProfile->bank_code) <span class="text-secondary">({{ $organization->paymentProfile->bank_code }})</span>@endif<br>
                            {{ $organization->paymentProfile->account_name }} · {{ $organization->paymentProfile->accountNumberForReview() ?? 'unreadable' }}
                            @if($organization->paymentProfile->resolved_account_name && $organization->paymentProfile->resolved_account_name !== $organization->paymentProfile->account_name)
                                <div class="text-danger">Bank says: {{ $organization->paymentProfile->resolved_account_name }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4"><strong>Identity</strong>
                        <div>{{ strtoupper($organization->paymentProfile->identity_type ?? '—') }} · {{ $organization->paymentProfile->identityNumberForReview() ?? 'unreadable' }}</div>
                    </div>
                    @if($organization->paymentProfile->review_notes)
                        <div class="col-12">
                            <div class="alert alert-{{ $status === 'rejected' ? 'danger' : 'warning' }} py-2 mb-0 mt-2">
                                <i class="bi bi-{{ $status === 'submitted' ? 'robot' : 'chat-left-text' }} me-1"></i>
                                <strong>{{ $status === 'submitted' ? 'Automatic approval declined:' : 'Review note:' }}</strong> {{ $organization->paymentProfile->review_notes }}
                            </div>
                        </div>
                    @endif
                </div>

                @if($organization->paymentProfile->status === 'submitted')
                    <div class="row g-3 mt-2">
                        <div class="col-md-7">
                            <form method="POST" action="{{ route('platform.payment.approve', $organization) }}">@csrf
                                <div class="input-group">
                                    <input class="form-control" name="paystack_subaccount_code" placeholder="Subaccount code (blank = create it)" aria-label="Paystack subaccount code">
                                    <input class="form-control" name="review_notes" placeholder="Optional note" aria-label="Approval note">
                                    <button class="btn btn-success"
                                        data-confirm-title="Enable live payments for {{ $organization->name }}?"
                                        data-confirm="They will start collecting real money immediately, settled to the bank account on this profile. Leaving the code blank makes HotFii open the subaccount at Paystack."
                                        data-confirm-icon="warning"
                                        data-confirm-button="Approve and go live">Approve</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-5">
                            <form method="POST" action="{{ route('platform.payment.reject', $organization) }}">@csrf
                                <div class="input-group">
                                    <input class="form-control" name="review_notes" placeholder="Required rejection reason" aria-label="Rejection reason" required>
                                    <button class="btn btn-outline-danger"
                                        data-confirm-title="Reject {{ $organization->name }}?"
                                        data-confirm="Live payments stay off and the owner sees your reason. They can correct the profile and resubmit."
                                        data-confirm-icon="danger"
                                        data-confirm-button="Reject profile">Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @empty
        <div class="p-5 text-center text-secondary">
            <i class="bi bi-check2-circle fs-3 d-block mb-2"></i>
            @if($filters['search'])
                No {{ $status }} profiles match that search.
            @elseif($status === 'submitted')
                Nothing waiting. Profiles Paystack can verify are approved automatically and only exceptions land here.
            @else
                No {{ $status }} profiles yet.
            @endif
        </div>
    @endforelse</div>
</div>
<div class="mt-3">{{ $organizations->links() }}</div>
@endsection
