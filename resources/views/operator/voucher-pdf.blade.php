<!doctype html>
<html>
<head>
<meta charset="utf-8">

<style>
@page {
    size: A4 landscape;
    margin: 5mm;
}

body {
    margin: 0;
    padding: 0;
    font-family: DejaVu Sans, sans-serif;
    color: #16231c;
    font-size: 7px;
}

.sheet-header {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1.5px solid #f4610a;
    margin-bottom: 2mm;
}

.sheet-header td {
    padding: 1mm 0 1.5mm;
}

.brand {
    color: #f4610a;
    font-size: 13px;
    font-weight: bold;
}

.sheet-sub {
    color: #68756d;
    font-size: 6.5px;
}

.sheet-meta {
    text-align: right;
    color: #68756d;
    font-size: 6.5px;
}

.voucher-row {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    page-break-inside: avoid;
}

.voucher-cell {
    width: 25%;
    padding: 0.65mm;
    vertical-align: top;
}

.voucher {
    height: 34mm;
    border: 0.7px dashed #8ca197;
    border-radius: 4px;
    padding: 1.4mm;
    overflow: hidden;
}

.voucher-head {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0.8mm;
}

.voucher-head td {
    padding: 0;
}

.company {
    color: #f4610a;
    font-size: 8px;
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
}

.plan {
    text-align: right;
    font-size: 6.5px;
    font-weight: bold;
    color: #263c31;
}

.divider {
    border-top: 0.5px solid #d9e2dd;
    margin-bottom: 1mm;
}

.main {
    width: 100%;
    border-collapse: collapse;
}

.main td {
    padding: 0;
    vertical-align: top;
}

.left {
    padding-right: 1.5mm !important;
}

.pin-label {
    color: #7a877f;
    font-size: 5.6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.pin {
    font-family: DejaVu Sans Mono, monospace;
    font-size: 10.5px;
    font-weight: bold;
    color: #111;
    white-space: nowrap;
    line-height: 1.15;
    margin: 0.3mm 0 0.7mm;
}

.serial {
    font-family: DejaVu Sans Mono, monospace;
    color: #68756d;
    font-size: 6px;
    white-space: nowrap;
    margin-bottom: 0.8mm;
}

.meta {
    width: 100%;
    border-collapse: collapse;
}

.meta td {
    padding: 0.15mm 0;
    font-size: 5.8px;
    line-height: 1.2;
}

.meta .label {
    width: 13mm;
    color: #728078;
}

.meta .value {
    font-weight: bold;
}

.qr {
    width: 14mm;
    text-align: right;
    vertical-align: middle !important;
}

.footer {
    border-top: 0.5px solid #d9e2dd;
    margin-top: 0.8mm;
    padding-top: 0.6mm;
    color: #6f7c75;
    font-size: 5px;
    text-align: center;
    line-height: 1.1;
}

.page-break {
    page-break-after: always;
}
</style>
</head>

<body>

@php
    $plan = $batch->accessPlan;

    $access = collect([
        $plan->duration_minutes
            ? number_format($plan->duration_minutes).' min'
            : null,
        $plan->dataAllowance(),
    ])->filter()->implode(' · ') ?: 'Unlimited';

    $pages = $batch->vouchers
        ->values()
        ->chunk(20);

    $pageCount = $pages->count();
@endphp


@foreach($pages as $pageIndex => $pageVouchers)

<table class="sheet-header">
<tr>
    <td>
        <span class="brand">HotFii</span>
        <span class="sheet-sub">Wi-Fi Access Vouchers</span>
    </td>

    <td class="sheet-meta">
        {{ $batch->reference }}
        · {{ $batch->quantity }} vouchers
        @if(($printParts ?? 1) > 1)
            · Part {{ $printPart }} / {{ $printParts }}
        @endif
        · Page {{ $pageIndex + 1 }} / {{ $pageCount }}
    </td>
</tr>
</table>


@foreach($pageVouchers->chunk(4) as $voucherRow)

<table class="voucher-row">
<tr>

@foreach($voucherRow as $voucher)

<td class="voucher-cell">

<div class="voucher">

    <table class="voucher-head">
    <tr>
        <td class="company">
            {{
                $batch->organization->branding['portal_name']
                ?? $batch->organization->name
            }}
        </td>

        <td class="plan">
            {{ $plan->name }}
        </td>
    </tr>
    </table>

    <div class="divider"></div>

    <table class="main">
    <tr>

        <td class="left">

            <div class="pin-label">
                Voucher PIN
            </div>

            <div class="pin">
                {{ $voucher->code_cipher }}
            </div>

            <div class="serial">
                Serial: {{ $voucher->serial_number }}
            </div>

            <table class="meta">

                <tr>
                    <td class="label">Access</td>
                    <td class="value">
                        {{ $access }}
                    </td>
                </tr>

                <tr>
                    <td class="label">Validity</td>
                    <td class="value">
                        {{ $plan->validityLabel() }}
                    </td>
                </tr>

                <tr>
                    <td class="label">Value</td>
                    <td class="value">
                        {{
                            $voucher->price_snapshot_kobo
                                ? '₦'.number_format(
                                    $voucher->price_snapshot_kobo / 100,
                                    0
                                )
                                : 'Complimentary'
                        }}
                    </td>
                </tr>

            </table>

        </td>

        <td class="qr">
            {!!
                QrCode::size(50)
                    ->margin(0)
                    ->generate($voucher->code_cipher)
            !!}
        </td>

    </tr>
    </table>

    <div class="footer">
        Valid on: {{ $batch->networkDevice?->name ?? 'All routers' }}
        · Scan QR or enter PIN · Validity starts on first use
    </div>

</div>

</td>

@endforeach


@for($empty = $voucherRow->count(); $empty < 4; $empty++)
    <td class="voucher-cell"></td>
@endfor

</tr>
</table>

@endforeach


@if(! $loop->last)
<div class="page-break"></div>
@endif

@endforeach

</body>
</html>
