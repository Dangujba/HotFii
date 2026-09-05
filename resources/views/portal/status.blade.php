<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access ready · HotFii</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="portal-shell py-4"><div class="container"><div class="card portal-card mx-auto"><div class="card-body p-4 p-md-5 text-center">
@if($voucher && $voucher->credential)
<div class="success-ring mx-auto mb-4"><i class="bi bi-check-lg"></i></div><h1 class="h3">Your access is ready</h1><p class="text-secondary">{{ $voucher->batch->accessPlan->name }} has been activated.</p>
<div class="row g-2 text-start my-4">
@if($allowance && $allowance['remaining_seconds'] !== null)<div class="col-6"><div class="p-3 rounded bg-body-tertiary"><div class="small text-secondary">Time remaining</div><strong>{{ gmdate('H:i:s', $allowance['remaining_seconds']) }}</strong></div></div>@endif
@if($allowance && $allowance['remaining_bytes'] !== null)<div class="col-6"><div class="p-3 rounded bg-body-tertiary"><div class="small text-secondary">Data remaining</div><strong>{{ number_format($allowance['remaining_bytes'] / 1048576, 1) }} MB</strong></div></div>@endif<div class="col-6"><div class="p-3 rounded bg-body-tertiary"><div class="small text-secondary">Valid until</div><strong>{{ $voucher->expires_at?->format('d M Y, H:i') ?? 'Plan allowance used' }}</strong></div></div><div class="col-6"><div class="p-3 rounded bg-body-tertiary"><div class="small text-secondary">Device limit</div><strong>{{ $voucher->batch->accessPlan->simultaneous_use }}</strong></div></div></div>
@if(
    $device->vendor === \App\Domain\Enums\RouterVendor::Unifi
    && ($portalContext['id'] ?? null)
)
    @if($errors->has('unifi'))
        <div class="alert alert-danger text-start">
            {{ $errors->first('unifi') }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.unifi-authorize', $device) }}">
        @csrf

        <input type="hidden" name="voucher" value="{{ $voucher->uuid }}">
        <input type="hidden" name="id" value="{{ $portalContext['id'] }}">
        <input type="hidden" name="ap" value="{{ $portalContext['ap'] ?? '' }}">
        <input type="hidden" name="ssid" value="{{ $portalContext['ssid'] ?? '' }}">
        <input type="hidden" name="url" value="{{ $portalContext['url'] ?? '' }}">

        <button class="btn btn-hotfii btn-lg w-100">
            Connect to Internet
        </button>
    </form>

@elseif($portalContext['link_login'] ?? null)

    <form method="POST" action="{{ $portalContext['link_login'] }}">
        <input type="hidden" name="username" value="{{ $voucher->credential->username }}">
        <input type="hidden" name="password" value="{{ $voucher->credential->password_cipher }}">

        <input type="hidden" name="dst" value="{{ route('portal.connected', [
            'device' => $device,
            'voucher' => $voucher->uuid,
            'orig' => $portalContext['link_orig'] ?? null,
            'mac' => $portalContext['mac'] ?? null,
        ]) }}">

        <button class="btn btn-hotfii btn-lg w-100">
            Connect to Internet
        </button>
    </form>

@else
    <div class="alert alert-info text-start">
        <strong>Laboratory credentials</strong>
        <div class="font-monospace mt-2">
            Username: {{ $voucher->credential->username }}<br>
            Password: {{ $voucher->credential->password_cipher }}
        </div>
        <div class="small mt-2">
            In a router redirect these are submitted automatically.
        </div>
    </div>
@endif
@else<div class="text-danger fs-1"><i class="bi bi-x-circle"></i></div><h1 class="h3 mt-3">Access record not found</h1><p class="text-secondary">Return to the portal and try the voucher again.</p><a class="btn btn-outline-secondary" href="{{ route('portal.show',$device) }}">Back to portal</a>@endif
</div></div></div></body></html>