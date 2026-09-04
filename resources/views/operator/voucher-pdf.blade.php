<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 14mm; } body { font-family: DejaVu Sans, sans-serif; color: #17221d; font-size: 10px; }
.header { border-bottom: 2px solid #f4610a; margin-bottom: 12px; padding-bottom: 8px; width: 100%; }
.header td { vertical-align: bottom; } .header .meta { text-align: right; color: #647067; }
.hotfii { color: #f4610a; font-size: 18px; font-weight: bold; }
/* One table per pair. dompdf ignores inline-block, so the two-up grid has to be
   real table cells, and a table per row keeps page breaks between cards. */
.row { width: 100%; page-break-inside: avoid; }
.row td { width: 50%; vertical-align: top; padding: 0 5px 10px; }
.card { border: 1px dashed #789186; border-radius: 8px; padding: 10px; }
.company { color: #f4610a; font-size: 13px; font-weight: bold; text-align: center; border-bottom: 1px solid #dfe5e1; padding-bottom: 5px; margin-bottom: 6px; }
.fields { width: 100%; } .fields td { padding: 1px 0; vertical-align: top; }
.fields .label { width: 54px; color: #647067; }
.fields .qr { width: 74px; vertical-align: top; }
/* The pin has to stay on one line however narrow the cell gets. */
.pin { font-family: DejaVu Sans Mono, monospace; font-size: 14px; font-weight: bold; letter-spacing: 1px; white-space: nowrap; }
.muted { color: #647067; } .hint { color: #647067; border-top: 1px solid #dfe5e1; margin-top: 6px; padding-top: 5px; }
</style></head><body>
<table class="header"><tr>
    <td><span class="hotfii">HotFii</span> <span class="muted">Wi-Fi access voucher</span></td>
    <td class="meta">{{ $batch->reference }} · {{ $batch->quantity }} vouchers</td>
</tr></table>
@php($plan = $batch->accessPlan)
@php($duration = collect([
    $plan->duration_minutes ? number_format($plan->duration_minutes).' min' : null,
    $plan->data_limit_bytes ? number_format($plan->data_limit_bytes / 1073741824, 2).' GB' : null,
])->filter()->implode(' · ') ?: 'Unlimited')
@foreach($batch->vouchers->chunk(2) as $pair)
<table class="row"><tr>
    @foreach($pair as $voucher)
    <td>
        <div class="card">
            <div class="company">{{ $batch->organization->branding['portal_name'] ?? $batch->organization->name }}</div>
            <table class="fields">
                <tr><td class="label">Plan</td><td>{{ $plan->name }}</td><td class="qr" rowspan="5">{!! QrCode::size(72)->margin(0)->generate($voucher->code_cipher) !!}</td></tr>
                <tr><td class="label">Pin</td><td class="pin">{{ $voucher->code_cipher }}</td></tr>
                <tr><td class="label">Duration</td><td>{{ $duration }}</td></tr>
                <tr><td class="label">Validity</td><td>{{ $plan->validity_days ? number_format($plan->validity_days).' days from first use' : 'No expiry' }}</td></tr>
                <tr><td class="label">Value</td><td>{{ $voucher->price_snapshot_kobo ? '₦'.number_format($voucher->price_snapshot_kobo / 100, 0) : 'Complimentary' }}</td></tr>
            </table>
            <div class="hint">Connect to Wi-Fi, open the sign-in page, and enter or scan this pin. Validity begins on first use.</div>
        </div>
    </td>
    @endforeach
    @if($pair->count() === 1)<td></td>@endif
</tr></table>
@endforeach
</body></html>
