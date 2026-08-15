<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 14mm; } body { font-family: DejaVu Sans, sans-serif; color: #17221d; font-size: 10px; }
.header { border-bottom: 2px solid #146c43; margin-bottom: 12px; padding-bottom: 8px; }
.voucher { width: 46%; display: inline-block; vertical-align: top; border: 1px dashed #789186; border-radius: 8px; margin: 0 1.2% 10px; padding: 10px; box-sizing: border-box; page-break-inside: avoid; }
.brand { color: #146c43; font-size: 16px; font-weight: bold; } .code { font-family: monospace; font-size: 15px; font-weight: bold; letter-spacing: 1px; margin: 8px 0; }
.qr { float: right; width: 74px; } .muted { color: #647067; } .clear { clear: both; }
</style></head><body>
<div class="header"><span class="brand">{{ $batch->organization->branding['portal_name'] ?? $batch->organization->name }}</span><span style="float:right">{{ $batch->reference }} · {{ $batch->quantity }} vouchers</span></div>
@foreach($batch->vouchers as $voucher)
<div class="voucher">
    <div class="qr">{!! QrCode::size(72)->margin(0)->generate($voucher->code_cipher) !!}</div>
    <div class="brand">HotFii Wi-Fi</div>
    <strong>{{ $batch->accessPlan->name }}</strong>
    <div class="code">{{ $voucher->code_cipher }}</div>
    <div>Value: {{ $voucher->price_snapshot_kobo ? '₦'.number_format($voucher->price_snapshot_kobo / 100, 0) : 'Complimentary' }}</div>
    <div class="muted">Connect to Wi-Fi, open the sign-in page, and enter or scan this code. Validity begins on first use.</div>
    <div class="clear"></div>
</div>
@endforeach
</body></html>