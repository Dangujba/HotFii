<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $batch->reference }} · Thermal vouchers</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef1ef;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #d8dfdb;
        }
        .toolbar strong { display: block; font-size: 15px; }
        .toolbar small { color: #627069; }
        .actions { display: flex; gap: 8px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 8px 13px;
            border: 1px solid #ccd5d0;
            border-radius: 6px;
            background: #fff;
            color: #17231d;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .button.primary { background: #f4610a; border-color: #f4610a; color: #fff; }
        .roll { width: {{ $paperWidth }}mm; margin: 18px auto; }
        .voucher {
            width: {{ $paperWidth }}mm;
            min-height: 72mm;
            margin: 0 0 10px;
            padding: 3.2mm 3mm 3.8mm;
            overflow: hidden;
            background: #fff;
            border: 1px dashed #68756d;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .brand { text-align: center; font-size: 17px; font-weight: 800; line-height: 1.15; }
        .powered { margin-top: 1mm; text-align: center; color: #59665f; font-size: 9px; }
        .rule { margin: 2.2mm 0; border-top: 1px dashed #111; }
        .plan { text-align: center; font-size: 13px; font-weight: 800; }
        .pin-label { margin-top: 2.4mm; text-align: center; font-size: 8px; text-transform: uppercase; }
        .pin {
            margin: 1mm 0 1.5mm;
            text-align: center;
            font-family: "Courier New", monospace;
            font-size: 21px;
            font-weight: 800;
            line-height: 1.1;
            overflow-wrap: anywhere;
        }
        .serial { text-align: center; font-family: "Courier New", monospace; font-size: 9px; }
        .qr { width: 25mm; height: 25mm; margin: 2.2mm auto; }
        .qr svg { display: block; width: 100%; height: 100%; }
        .meta { width: 100%; border-collapse: collapse; font-size: 9px; }
        .meta td { padding: 0.65mm 0; vertical-align: top; }
        .meta td:first-child { width: 17mm; color: #5e6a64; }
        .meta td:last-child { text-align: right; font-weight: 700; }
        .footer { margin-top: 2.2mm; text-align: center; font-size: 8px; line-height: 1.35; }
        .reference { margin-top: 2mm; text-align: center; color: #66736c; font-size: 7.5px; }

        @media print {
            @page { size: {{ $paperWidth }}mm 76mm; margin: 0; }
            body { width: {{ $paperWidth }}mm; background: #fff; }
            .toolbar { display: none !important; }
            .roll { width: {{ $paperWidth }}mm; margin: 0; }
            .voucher {
                margin: 0;
                border: 0;
                page-break-after: always;
                break-after: page;
            }
            .voucher:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
@php
    $plan = $batch->accessPlan;
    $organizationName = $batch->organization->branding['portal_name'] ?? $batch->organization->name;
    $access = collect([
        $plan->duration_minutes ? number_format($plan->duration_minutes).' min' : null,
        $plan->dataAllowance(),
    ])->filter()->implode(' · ') ?: 'Unlimited';
    $pdfRoute = ['batch' => $batch];
    if (($printParts ?? 1) > 1) {
        $pdfRoute['part'] = $printPart;
    }
@endphp

<header class="toolbar">
    <div>
        <strong>Thermal vouchers · {{ $paperWidth }} mm</strong>
        <small>
            {{ $batch->reference }} · {{ $batch->vouchers->count() }} vouchers
            @if(($printParts ?? 1) > 1)
                · part {{ $printPart }} of {{ $printParts }}
            @endif
        </small>
    </div>
    <div class="actions">
        <a class="button" href="{{ route('vouchers.print', $pdfRoute) }}">A4 PDF</a>
        <button class="button primary" type="button" onclick="window.print()">Print</button>
    </div>
</header>

<main class="roll">
@foreach($batch->vouchers as $voucher)
    <article class="voucher">
        <div class="brand">{{ $organizationName }}</div>
        <div class="powered">Wi-Fi access · Powered by HotFii</div>
        <div class="rule"></div>
        <div class="plan">{{ $plan->name }}</div>
        <div class="pin-label">Voucher PIN</div>
        <div class="pin">{{ $voucher->code_cipher }}</div>
        <div class="serial">{{ $voucher->serial_number }}</div>
        <div class="qr">{!! QrCode::size(110)->margin(0)->generate($voucher->code_cipher) !!}</div>
        <table class="meta">
            <tr><td>Access</td><td>{{ $access }}</td></tr>
            <tr><td>Validity</td><td>{{ $plan->validityLabel() }}</td></tr>
            <tr><td>Value</td><td>{{ $voucher->price_snapshot_kobo ? '₦'.number_format($voucher->price_snapshot_kobo / 100, 0) : 'Complimentary' }}</td></tr>
            <tr><td>Valid on</td><td>{{ $batch->networkDevice?->name ?? 'All routers' }}</td></tr>
        </table>
        <div class="footer">Scan the QR code or enter the PIN.<br>Validity starts on first use.</div>
        <div class="reference">{{ $batch->reference }}</div>
    </article>
@endforeach
</main>
</body>
</html>
