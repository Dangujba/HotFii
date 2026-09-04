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
.inner { width: 100%; } .inner td { padding: 0; vertical-align: top; }
.qr { width: 74px; text-align: right; }
.company { font-size: 13px; font-weight: bold; }
.code { font-family: DejaVu Sans Mono, monospace; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin: 6px 0; }
.muted { color: #647067; } .hint { color: #647067; margin-top: 6px; }
</style></head><body>
<table class="header"><tr>
    <td><span class="hotfii">HotFii</span> <span class="muted">Wi-Fi access voucher</span></td>
    <td class="meta">{{ $batch->organization->branding['portal_name'] ?? $batch->organization->name }}<br>{{ $batch->reference }} · {{ $batch->quantity }} vouchers</td>
</tr></table>
@foreach($batch->vouchers->chunk(2) as $pair)
<table class="row"><tr>
    @foreach($pair as $voucher)
    <td>
        <div class="card">
            <table class="inner"><tr>
                <td>
                    <div class="company">{{ $batch->organization->branding['portal_name'] ?? $batch->organization->name }}</div>
                    <strong>{{ $batch->accessPlan->name }}</strong>
                    <div class="code">{{ $voucher->code_cipher }}</div>
                    <div>Value: {{ $voucher->price_snapshot_kobo ? '₦'.number_format($voucher->price_snapshot_kobo / 100, 0) : 'Complimentary' }}</div>
                </td>
                <td class="qr">{!! QrCode::size(72)->margin(0)->generate($voucher->code_cipher) !!}</td>
            </tr></table>
            <div class="hint">Connect to Wi-Fi, open the sign-in page, and enter or scan this code. Validity begins on first use.</div>
        </div>
    </td>
    @endforeach
    @if($pair->count() === 1)<td></td>@endif
</tr></table>
@endforeach
</body></html>
