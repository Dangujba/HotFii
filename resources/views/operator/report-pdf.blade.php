@php
    $company = $organization->branding['portal_name'] ?? $organization->name;
    $gross = $summary->gross_kobo ?? 0;
    $fees = ($summary->gateway_kobo ?? 0) + ($summary->platform_kobo ?? 0);
    $naira = fn ($kobo) => '₦'.number_format($kobo / 100, 2);
    $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
@endphp
<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 16mm 14mm 20mm; }
body { font-family: DejaVu Sans, sans-serif; color: #17221d; font-size: 9.5px; line-height: 1.45; }
.masthead { width: 100%; border-bottom: 2px solid #f4610a; padding-bottom: 8px; }
.masthead td { vertical-align: bottom; } .masthead .right { text-align: right; }
.wordmark { color: #f4610a; font-size: 20px; font-weight: bold; letter-spacing: -0.4px; }
.company { font-size: 12px; font-weight: bold; } .muted { color: #647067; }
h1 { font-size: 16px; margin: 14px 0 2px; } h2 { font-size: 11px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.8px; color: #647067; }
.period { color: #647067; margin-bottom: 4px; }
/* Summary tiles. dompdf ignores inline-block, so these are table cells. */
.tiles { width: 100%; margin-top: 12px; border-collapse: separate; border-spacing: 6px 0; }
.tiles td { width: 25%; background: #f6f8f7; border: 1px solid #dfe5e1; border-radius: 6px; padding: 8px 9px; vertical-align: top; }
.tiles .k { color: #647067; font-size: 8px; text-transform: uppercase; letter-spacing: 0.6px; }
.tiles .v { font-size: 14px; font-weight: bold; padding-top: 2px; }
.tiles .s { color: #647067; font-size: 8px; }
table.data { width: 100%; border-collapse: collapse; }
table.data th { background: #17221d; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.7px; text-align: left; padding: 5px 7px; }
table.data td { border-bottom: 1px solid #eef1ef; padding: 5px 7px; }
table.data tr.alt td { background: #f9fbfa; }
table.data .num { text-align: right; } table.data .total td { border-top: 1.5px solid #17221d; border-bottom: none; font-weight: bold; background: #fff; }
.note { color: #647067; margin-top: 6px; font-size: 8.5px; }
.empty { color: #647067; text-align: center; padding: 14px 0; }
.footer { position: fixed; left: 0; right: 0; bottom: -14mm; border-top: 1px solid #dfe5e1; padding-top: 4px; color: #647067; font-size: 8px; }
.footer table { width: 100%; } .footer .right { text-align: right; }
.footer .page:after { content: counter(page); }
</style></head><body>
<div class="footer"><table><tr>
    <td>HotFii · {{ $company }}</td>
    <td class="right">Generated {{ $generatedAt->format('j M Y, H:i') }} · Page <span class="page"></span></td>
</tr></table></div>

<table class="masthead"><tr>
    <td><span class="wordmark">HotFii</span></td>
    <td class="right"><div class="company">{{ $company }}</div><div class="muted">Sales &amp; usage report</div></td>
</tr></table>

<h1>Sales &amp; Usage Report</h1>
<div class="period">{{ $from->format('j M Y') }} – {{ $to->format('j M Y') }} · {{ number_format($days) }} {{ $days == 1 ? 'day' : 'days' }}</div>

<table class="tiles"><tr>
    <td><div class="k">Gross paid sales</div><div class="v">{{ $naira($gross) }}</div><div class="s">{{ number_format($summary->sales ?? 0) }} of {{ number_format($summary->attempts ?? 0) }} attempts settled</div></td>
    <td><div class="k">Fees deducted</div><div class="v">{{ $naira($fees) }}</div><div class="s">{{ $naira($summary->gateway_kobo ?? 0) }} gateway · {{ $naira($summary->platform_kobo ?? 0) }} platform</div></td>
    <td><div class="k">Net to operator</div><div class="v">{{ $naira($gross - $fees) }}</div><div class="s">{{ $gross > 0 ? number_format(($gross - $fees) / $gross * 100, 1) : '0.0' }}% of gross</div></td>
    <td><div class="k">Access delivered</div><div class="v">{{ number_format($usage->sessions ?? 0) }}</div><div class="s">sessions · {{ \App\Support\Bytes::human($usage->bytes ?? 0) }} transferred</div></td>
</tr></table>

<h2>Sales by channel</h2>
<table class="data">
    <thead><tr><th>Channel</th><th class="num">Sales</th><th class="num">Gross</th><th class="num">Share</th></tr></thead>
    <tbody>
        @forelse($byChannel as $channel)
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ ucfirst($channel->channel) }}</td><td class="num">{{ number_format($channel->sales) }}</td><td class="num">{{ $naira($channel->total) }}</td><td class="num">{{ $gross > 0 ? number_format($channel->total / $gross * 100, 1) : '0.0' }}%</td></tr>
        @empty
        <tr><td colspan="4" class="empty">No settled sales in this period.</td></tr>
        @endforelse
        @if($byChannel->isNotEmpty())
        <tr class="total"><td>Total</td><td class="num">{{ number_format($byChannel->sum('sales')) }}</td><td class="num">{{ $naira($byChannel->sum('total')) }}</td><td class="num">100.0%</td></tr>
        @endif
    </tbody>
</table>

<h2>Top plans</h2>
<table class="data">
    <thead><tr><th>Plan</th><th class="num">Sales</th><th class="num">Gross</th><th class="num">Average</th></tr></thead>
    <tbody>
        @forelse($topPlans as $plan)
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ $plan->name }}</td><td class="num">{{ number_format($plan->sales) }}</td><td class="num">{{ $naira($plan->total) }}</td><td class="num">{{ $naira($plan->sales ? $plan->total / $plan->sales : 0) }}</td></tr>
        @empty
        <tr><td colspan="4" class="empty">No settled sales in this period.</td></tr>
        @endforelse
    </tbody>
</table>
@if($topPlans->count() === 10)<div class="note">Ten highest-earning plans shown.</div>@endif

<h2>Daily paid sales</h2>
<table class="data">
    <thead><tr><th>Date</th><th class="num">Sales</th><th class="num">Gross</th></tr></thead>
    <tbody>
        @forelse($daily as $day)
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ \Carbon\Carbon::parse($day->day)->format('D j M Y') }}</td><td class="num">{{ number_format($day->sales) }}</td><td class="num">{{ $naira($day->total) }}</td></tr>
        @empty
        <tr><td colspan="3" class="empty">No settled sales in this period.</td></tr>
        @endforelse
        @if($daily->isNotEmpty())
        <tr class="total"><td>Total</td><td class="num">{{ number_format($daily->sum('sales')) }}</td><td class="num">{{ $naira($daily->sum('total')) }}</td></tr>
        @endif
    </tbody>
</table>

<h2>Transactions</h2>
<table class="data">
    <thead><tr><th>Date</th><th>Reference</th><th>Plan</th><th>Channel</th><th>Status</th><th class="num">Gross</th></tr></thead>
    <tbody>
        @forelse($rows as $row)
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ $row->created_at->format('j M, H:i') }}</td><td>{{ $row->reference }}</td><td>{{ $row->accessPlan?->name ?? '—' }}</td><td>{{ ucfirst($row->channel) }}</td><td>{{ ucfirst($row->status instanceof BackedEnum ? $row->status->value : $row->status) }}</td><td class="num">{{ $naira($row->gross_amount_kobo) }}</td></tr>
        @empty
        <tr><td colspan="6" class="empty">No transactions in this period.</td></tr>
        @endforelse
    </tbody>
</table>
@if(($summary->attempts ?? 0) > $rowLimit)<div class="note">Showing the first {{ number_format($rowLimit) }} of {{ number_format($summary->attempts) }} transactions. Export the CSV for the complete ledger.</div>@endif
</body></html>
