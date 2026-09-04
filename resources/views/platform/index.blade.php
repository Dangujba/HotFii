@extends('layouts.platform')
@section('title', 'Platform Overview')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Platform Overview</h1>
            <p class="text-secondary mb-0">Organizations, payment reviews, and runtime health</p>
        </div>
    </div>
    <div class="row g-3 mb-4">
        @foreach([['Organizations', $stats['organizations'], 'buildings', 'primary'], ['Collecting payments', $stats['live_organizations'], 'broadcast-pin', 'success'], ['Monthly processed volume', '₦' . number_format($stats['monthly_volume'] / 100, 0), 'cash-stack', 'info'], ['Payment reviews', $stats['pending_reviews'], 'person-check', 'warning']] as [$label, $value, $icon, $color])
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card">
                    <div class="card-body">
                        <div class="text-secondary">{{ $label }}</div>
                        <div class="fs-3 fw-bold">{{ $value }}</div><i class="bi bi-{{ $icon }} text-{{ $color }}"></i>
                    </div>
                </div>
        </div>@endforeach
    </div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card metric-card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Live payment requests</h2>
                </div>
                <div class="card-body p-0">@forelse($paymentRequests as $organization)
                    <div class="p-4 border-bottom">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="h6 mb-1">{{ $organization->name }}</h3><span
                                    class="text-secondary">{{ ucfirst($organization->mode->value) }} · submitted
                                    {{ $organization->paymentProfile?->submitted_at?->diffForHumans() }}</span>
                            </div><span class="badge text-bg-warning">Review</span>
                        </div>
                        @if($organization->paymentProfile)
                            <div class="row g-2 small mt-3">
                                <div class="col-md-4"><strong>Contact</strong>
                                    <div>
                                        {{ $organization->paymentProfile->contact_name }}<br>{{ $organization->paymentProfile->contact_phone }}
                                    </div>
                                </div>
                                <div class="col-md-4"><strong>Settlement</strong>
                                    <div>
                                        {{ $organization->paymentProfile->bank_name }}@if($organization->paymentProfile->bank_code) <span class="text-secondary">({{ $organization->paymentProfile->bank_code }})</span>@endif<br>{{ $organization->paymentProfile->account_name }}
                                        · {{ $organization->paymentProfile->account_number_cipher }}
                                        @if($organization->paymentProfile->resolved_account_name && $organization->paymentProfile->resolved_account_name !== $organization->paymentProfile->account_name)
                                            <div class="text-danger">Bank says: {{ $organization->paymentProfile->resolved_account_name }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4"><strong>Identity</strong>
                                    <div>{{ strtoupper($organization->paymentProfile->identity_type) }} ·
                                        {{ $organization->paymentProfile->identity_number_cipher }}</div>
                                </div>
                                @if($organization->paymentProfile->review_notes)
                                    <div class="col-12">
                                        <div class="alert alert-warning py-2 mb-0 mt-2"><i class="bi bi-robot me-1"></i><strong>Automatic approval declined:</strong> {{ $organization->paymentProfile->review_notes }}</div>
                                    </div>
                                @endif
                            </div>
                            <div class="row g-3 mt-2">
                                <div class="col-md-7">
                                    <form method="POST" action="{{ route('platform.payment.approve', $organization) }}">@csrf
                                        <div class="input-group"><input class="form-control" name="paystack_subaccount_code"
                                                placeholder="Subaccount code (blank = create it)"><input class="form-control"
                                                name="review_notes" placeholder="Optional note"><button
                                                class="btn btn-success"
                                                data-confirm-title="Enable live payments for {{ $organization->name }}?"
                                                data-confirm="They will start collecting real money immediately, settled to the bank account on this profile. Leaving the code blank makes HotFii open the subaccount at Paystack."
                                                data-confirm-icon="warning"
                                                data-confirm-button="Approve and go live">Approve</button></div>
                                    </form>
                                </div>
                                <div class="col-md-5">
                                    <form method="POST" action="{{ route('platform.payment.reject', $organization) }}">@csrf<div
                                            class="input-group"><input class="form-control" name="review_notes"
                                                placeholder="Required rejection reason" required><button
                                                class="btn btn-outline-danger"
                                                data-confirm-title="Reject {{ $organization->name }}?"
                                                data-confirm="Live payments stay off and the owner sees your reason. They can correct the profile and resubmit."
                                                data-confirm-icon="danger"
                                                data-confirm-button="Reject profile">Reject</button></div>
                                    </form>
                                </div>
                        </div>@endif
                </div>@empty<div class="p-5 text-center text-secondary"><i class="bi bi-check2-circle fs-3 d-block mb-2"></i>Nothing waiting. Profiles Paystack can verify are approved automatically and only exceptions land here.</div>@endforelse
                </div>
            </div>
            <div class="card metric-card">
                <div class="card-header">
                    <h2 class="h5 mb-0">Recent organizations</h2>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Support</th>
                            </tr>
                        </thead>
                        <tbody>@foreach($recentOrganizations as $organization)
                            <tr>
                                <td>{{ $organization->name }}</td>
                                <td>{{ ucfirst($organization->mode->value) }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($organization->status->value)) }}</td>
                                <td>{{ $organization->created_at->diffForHumans() }}</td>
                                <td>
                                    <form class="d-flex gap-2" method="POST"
                                        action="{{ route('platform.impersonate.start', $organization) }}">@csrf<input
                                            class="form-control form-control-sm" name="reason" minlength="10"
                                            placeholder="Written support reason" required><button
                                            class="btn btn-sm btn-outline-primary"
                                            data-confirm-title="Enter support mode for {{ $organization->name }}?"
                                            data-confirm="You will see their customers, sessions and finances as they do. The reason you typed is written to the audit log against your account."
                                            data-confirm-icon="warning"
                                            data-confirm-button="Enter support mode">Open</button></form>
                                </td>
                        </tr>@endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card metric-card">
                <div class="card-header">
                    <h2 class="h5 mb-0">System & queue health</h2>
                </div>
                <div class="list-group list-group-flush">
                    @foreach(['Redis' => $health['redis'], 'Reverb' => $health['reverb'], 'Queued jobs' => $health['queued_jobs'], 'Failed jobs' => $health['failed_jobs'], 'Pending webhooks' => $health['pending_webhooks']] as $label => $value)
                        <div class="list-group-item d-flex justify-content-between">
                    <span>{{ $label }}</span><strong>{{ $value }}</strong></div>@endforeach
                </div>
            </div>
            <div class="alert alert-info mt-4"><i class="bi bi-info-circle me-2"></i>Laravel native Redis workers are used
                until the Horizon package publishes Laravel 13 compatibility.</div>
        </div>
    </div>
@endsection