<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>Connect · {{ $device->organization->name }}</title>
<link rel="icon" type="image/png" href="{{ asset('images/hotfii-icon.png') }}">
@vite(['resources/css/app.css','resources/js/app.js'])
<style>:root{--hotfii-primary:{{ $device->organization->branding['primary_color'] ?? '#f4610a' }};}</style>
</head>
<body class="portal-shell py-4">
<div class="container"><div class="card portal-card mx-auto">
<div class="card-body p-4 p-md-5">
<div class="text-center mb-4"><img src="{{ asset('images/hotfii-icon.png') }}" alt="HotFii Logo" style="height: 48px; width: 48px; border-radius: 12px;" class="mb-3"><h1 class="h3 mt-3 mb-1">{{ $device->organization->branding['portal_name'] ?? $device->organization->name }}</h1><p class="text-secondary">{{ $device->location->name }} · Secure Wi-Fi access</p></div>
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<ul class="nav nav-pills nav-fill mb-4" role="tablist">
@if($device->organization->sellsAccess())<li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#buy">Buy access</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#voucher">Voucher</button></li>@endif
@if($device->organization->mode->value !== 'commerce')<li class="nav-item"><button class="nav-link {{ $device->organization->sellsAccess() ? '' : 'active' }}" data-bs-toggle="pill" data-bs-target="#staff">Staff / member</button></li>@endif
</ul>
<div class="tab-content">
@if($device->organization->sellsAccess())
<div class="tab-pane fade show active" id="buy">
<form id="payment-form">
<div class="vstack gap-2 mb-3">@forelse($plans->where('access_type','paid') as $plan)<label class="plan-option"><input class="form-check-input me-2" type="radio" name="access_plan_uuid" value="{{ $plan->uuid }}" @checked($loop->first)><span><strong>{{ $plan->name }}</strong><small>@if($plan->duration_minutes){{ $plan->duration_minutes }} minutes @endif @if($plan->data_limit_bytes)· {{ number_format($plan->data_limit_bytes / 1073741824, 2) }} GB @endif @if($plan->download_kbps)· {{ number_format($plan->download_kbps / 1000, 1) }} Mbps @endif</small></span><strong class="ms-auto">₦{{ number_format($plan->price_kobo / 100, 0) }}</strong></label>@empty<div class="alert alert-warning">No paid plans are available.</div>@endforelse</div>
<div class="mb-3"><label class="form-label">Email for payment receipt</label><input class="form-control" type="email" name="email" required></div>
<div class="mb-3"><label class="form-label">Phone <span class="text-secondary">(optional)</span></label><input class="form-control" name="phone" inputmode="tel"></div>
@foreach($portalContext as $key=>$value)@if($value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
<button class="btn btn-hotfii btn-lg w-100" @disabled($plans->where('access_type','paid')->isEmpty())><span class="payment-label">Pay securely with Paystack</span><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span></button>
<div id="payment-error" class="text-danger small mt-2"></div>
</form>
</div>
<div class="tab-pane fade" id="voucher">
<form method="POST" action="{{ route('portal.redeem',$device) }}">@csrf
<div class="mb-3"><label class="form-label">Printed voucher code</label><input id="voucher-code" class="form-control form-control-lg text-uppercase font-monospace" name="voucher_code" value="{{ old('voucher_code') }}" placeholder="HF-XXXX-XXXX-XXXX" required></div>
<div class="mb-3"><label class="form-label">Phone <span class="text-secondary">(optional)</span></label><input class="form-control" name="phone" inputmode="tel"></div>
@foreach($portalContext as $key=>$value)@if($value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
<label class="btn btn-outline-secondary w-100 mb-2"><i class="bi bi-qr-code-scan me-2"></i>Scan QR from voucher<input id="qr-file" class="d-none" type="file" accept="image/*" capture="environment"></label>
<div id="qr-message" class="small text-secondary mb-3"></div><button class="btn btn-hotfii btn-lg w-100">Activate voucher</button>
</form>
</div>
@endif
@if($device->organization->mode->value !== 'commerce')
<div class="tab-pane fade {{ $device->organization->sellsAccess() ? '' : 'show active' }}" id="staff">
@if($portalContext['link_login'] ?? null)<form method="POST" action="{{ $portalContext['link_login'] }}"><input type="hidden" name="dst" value="{{ $portalContext['link_orig'] ?? '' }}"><div class="mb-3"><label class="form-label">Username</label><input class="form-control form-control-lg" name="username" required></div><div class="mb-3"><label class="form-label">Password</label><input class="form-control form-control-lg" type="password" name="password" required></div><button class="btn btn-hotfii btn-lg w-100">Connect</button></form>@else<div class="alert alert-info">Staff credentials are ready for router testing. Open this page through the router captive-portal redirect to complete sign-in.</div>@endif
</div>
@endif
</div>
<hr class="my-4"><div class="small text-center text-secondary"><i class="bi bi-shield-check me-1"></i>Powered by HotFii · Router: {{ $device->name }}</div>
</div></div></div>
<script>
document.getElementById('payment-form')?.addEventListener('submit', async function(event) {
    event.preventDefault();
    const button = this.querySelector('button[type="submit"]');
    button.disabled = true; button.querySelector('.payment-label').textContent = 'Starting payment…'; button.querySelector('.spinner-border').classList.remove('d-none');
    document.getElementById('payment-error').textContent = '';
    try {
        const response = await fetch(@json(route('portal.payment',$device)), {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:new FormData(this)});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Could not start payment.');
        window.location.assign(data.authorization_url);
    } catch (error) {
        document.getElementById('payment-error').textContent = error.message;
        button.disabled = false; button.querySelector('.payment-label').textContent = 'Pay securely with Paystack'; button.querySelector('.spinner-border').classList.add('d-none');
    }
});
document.getElementById('qr-file')?.addEventListener('change', async function() {
    const message = document.getElementById('qr-message');
    if (!('BarcodeDetector' in window)) { message.textContent = 'QR scanning is not supported by this browser. Type the printed code instead.'; return; }
    try { const bitmap = await createImageBitmap(this.files[0]); const results = await new BarcodeDetector({formats:['qr_code']}).detect(bitmap); if (!results.length) throw new Error('No QR code found.'); document.getElementById('voucher-code').value = results[0].rawValue; message.textContent = 'Voucher QR read successfully.'; } catch (error) { message.textContent = error.message; }
});
</script>
</body></html>