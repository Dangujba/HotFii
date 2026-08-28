@extends('layouts.app')
@section('title', $device->name)
@section('heading', $device->name)
@section('subheading', $device->vendor->label().' · '.$device->location->name)
@section('actions')
<form class="d-inline" method="POST" action="{{ route('network.devices.test', $device) }}">@csrf<button class="btn btn-hotfii"><i class="bi bi-activity me-1"></i>Run readiness tests</button></form>
@endsection
@section('content')
@if(session('provisioning_url'))
<div class="alert alert-info"><strong>Temporary provisioning URL</strong><div class="input-group mt-2"><input id="provisioning-url" class="form-control font-monospace" readonly value="{{ session('provisioning_url') }}"><button class="btn btn-outline-secondary btn-copy-inline" type="button" data-copy-target="#provisioning-url" data-copy-message="Provisioning URL copied"><i class="bi bi-clipboard me-1"></i><span data-copy-label>Copy</span></button></div><div class="small mt-1">Expires after 15 minutes. Treat it like a password.</div></div>
@endif
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card metric-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Integration Test Center</h2><span class="badge text-bg-{{ $device->status->value === 'online' ? 'success' : ($device->status->value === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($device->status->value) }}</span></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Check</th><th>Result</th><th>Details</th><th>Checked</th></tr></thead>
                <tbody>@forelse($tests as $test)
                    <tr><td class="fw-semibold">{{ str_replace('_', ' ', ucfirst($test->test_key)) }}</td><td><span class="badge text-bg-{{ $test->status === 'passed' ? 'success' : ($test->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($test->status) }}</span></td><td>{{ $test->message }}</td><td>{{ $test->checked_at?->diffForHumans() }}</td></tr>
                @empty<tr><td colspan="4" class="text-center text-secondary py-5">No test run yet. Provision the device, then run readiness tests.</td></tr>@endforelse</tbody>
            </table></div></div>
        </div>

        <div class="card metric-card">
            <div class="card-header"><h2 class="h5 mb-0">{{ $provisioning['method'] === 'script' ? 'RouterOS provisioning script' : 'Guided RADIUS configuration' }}</h2></div>
            <div class="card-body">
                @if($provisioning['method'] === 'script')
                    <div class="alert alert-warning"><i class="bi bi-shield-lock me-2"></i>This script contains the RADIUS secret. Run it only on the intended RouterOS 7 device.</div>
                    <div class="code-block">
                        <button class="btn-copy" type="button" data-copy-target="#provisioning-script" data-copy-message="Provisioning script copied"><i class="bi bi-clipboard me-1"></i><span data-copy-label>Copy script</span></button>
                        <pre class="provisioning-script"><code id="provisioning-script">{{ $provisioning['script'] }}</code></pre>
                    </div>
                    <p class="small text-secondary mt-2 mb-0">Paste the whole block into <span class="font-monospace">New Terminal</span> in Winbox or WebFig.</p>
                @else
                    <div class="row g-3">
                        @foreach(['radius_host' => 'RADIUS server', 'authentication_port' => 'Authentication port', 'accounting_port' => 'Accounting port', 'coa_port' => 'CoA port', 'nas_identifier' => 'NAS identifier', 'radius_secret' => 'RADIUS secret', 'portal_url' => 'Captive portal URL', 'heartbeat_url' => 'Heartbeat URL'] as $key => $label)
                            @php($value = $provisioning[$key] ?? null)
                            <div class="col-md-6">
                                <label class="small text-secondary" for="prov-{{ $key }}">{{ $label }}</label>
                                <div class="input-group">
                                    <div id="prov-{{ $key }}" class="form-control bg-light font-monospace text-break">{{ $value ?? 'Not applicable' }}</div>
                                    @if(filled($value))
                                        <button class="btn btn-outline-secondary btn-copy-inline" type="button" data-copy-target="#prov-{{ $key }}" data-copy-message="{{ $label }} copied" title="Copy {{ $label }}" aria-label="Copy {{ $label }}"><i class="bi bi-clipboard"></i></button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <hr><strong>Walled garden domains</strong><ul class="mt-2">@foreach($provisioning['walled_garden'] as $host)<li><code>{{ $host }}</code></li>@endforeach</ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card metric-card mb-4"><div class="card-header"><h2 class="h5 mb-0">Device details</h2></div><div class="card-body">
            <dl class="row mb-0"><dt class="col-5">Vendor</dt><dd class="col-7">{{ $device->vendor->label() }}</dd><dt class="col-5">Model</dt><dd class="col-7">{{ $device->model ?: 'Not reported' }}</dd><dt class="col-5">Firmware</dt><dd class="col-7">{{ $device->firmware_version ?: 'Not reported' }}</dd><dt class="col-5">Support</dt><dd class="col-7">{{ ucfirst($device->support_level->value) }}</dd><dt class="col-5">Heartbeat</dt><dd class="col-7">{{ $device->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</dd><dt class="col-5">NAS ID</dt><dd class="col-7"><code>{{ $device->nas_identifier }}</code></dd></dl>
        </div></div>
        <div class="card metric-card mb-4"><div class="card-header"><h2 class="h5 mb-0">Capabilities</h2></div><div class="card-body d-flex flex-wrap gap-2">@foreach($device->capabilities ?? [] as $capability)<span class="badge text-bg-light border">{{ str_replace('_', ' ', $capability) }}</span>@endforeach</div></div>
        <div class="card metric-card"><div class="card-body"><h2 class="h5">Safer remote setup</h2><p class="text-secondary">Generate a short-lived download URL instead of copying the secret through chat.</p><form method="POST" action="{{ route('network.devices.provisioning-link', $device) }}">@csrf<button class="btn btn-hotfii w-100" data-confirm-title="Generate a provisioning link?" data-confirm="Anyone holding the link can read {{ $device->name }}'s RADIUS secret until it expires in 15 minutes." data-confirm-icon="warning" data-confirm-button="Generate link"><i class="bi bi-link-45deg me-1"></i>Generate 15-minute link</button></form></div></div>
    </div>
</div>
@endsection