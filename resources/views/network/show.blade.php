@extends('layouts.app')
@section('title', $device->name)
@section('heading', $device->name)
@section('subheading', $device->vendor->label().' · '.$device->location->name)
@section('actions')
<div class="d-flex flex-wrap gap-2">

    <a
        href="{{ route('network.devices.guide', [
            'device' => $device,
            'lang' => 'en',
        ]) }}"
        class="btn btn-outline-secondary"
    >
        <i class="bi bi-book me-1"></i>
        Setup Guide
    </a>

    <form
        class="d-inline"
        method="POST"
        action="{{ route('network.devices.test', $device) }}"
    >
        @csrf
        <button class="btn btn-hotfii">
            <i class="bi bi-activity me-1"></i>
            Run readiness tests
        </button>
    </form>

</div>
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

        @if($device->vendor === \App\Domain\Enums\RouterVendor::Unifi)
        <div class="card metric-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">UniFi Cloud Connection</h2>

                @php($unifiConfig = $device->management_config ?? [])

                @if(filled($unifiConfig['site_id'] ?? null))
                    <span class="badge text-bg-success">Configured</span>
                @elseif(filled($unifiConfig['api_key'] ?? null))
                    <span class="badge text-bg-warning">Site required</span>
                @else
                    <span class="badge text-bg-secondary">Not connected</span>
                @endif
            </div>

            <div class="card-body">

                @if($errors->has('unifi_api'))
                    <div class="alert alert-danger">
                        {{ $errors->first('unifi_api') }}
                    </div>
                @endif

                @if(filled($unifiConfig['site_id'] ?? null))
                    <div class="alert alert-success">
                        <strong>UniFi connected.</strong>
                        HotFii is linked to
                        <strong>{{ $unifiConfig['site_name'] ?? 'the selected UniFi site' }}</strong>.
                    </div>

                    <dl class="row mb-4">
                        <dt class="col-md-4">Site</dt>
                        <dd class="col-md-8">{{ $unifiConfig['site_name'] ?? 'UniFi Site' }}</dd>

                        <dt class="col-md-4">Site ID</dt>
                        <dd class="col-md-8"><code>{{ $unifiConfig['site_id'] }}</code></dd>

                        <dt class="col-md-4">Console</dt>
                        <dd class="col-md-8"><code>{{ $unifiConfig['host_id'] ?? 'Unknown' }}</code></dd>

                        <dt class="col-md-4">API Key</dt>
                        <dd class="col-md-8"><span class="text-success">Stored securely</span></dd>
                    </dl>
                @else
                    <p class="text-secondary">
                        Connect HotFii to your UniFi account using a UniFi API key.
                        The key is stored encrypted and is never shown again.
                    </p>
                @endif

                <form method="POST"
                      action="{{ route('network.devices.unifi.discover', $device) }}"
                      class="mb-4">
                    @csrf

                    <label class="form-label" for="unifi-api-key">
                        {{ filled($unifiConfig['api_key'] ?? null) ? 'Replace / reconnect API key' : 'UniFi API Key' }}
                    </label>

                    <div class="input-group">
                        <input
                            id="unifi-api-key"
                            type="password"
                            name="api_key"
                            class="form-control"
                            autocomplete="new-password"
                            placeholder="Paste UniFi API key"
                            required
                        >
                        <button class="btn btn-hotfii" type="submit">
                            <i class="bi bi-cloud-check me-1"></i>
                            Discover sites
                        </button>
                    </div>

                    <div class="form-text">
                        HotFii uses this key to discover the UniFi consoles and sites available to your account.
                    </div>
                </form>

                @if(session('unifi_sites'))
                    <hr>

                    <h3 class="h6">Select the UniFi site to manage</h3>
                    <p class="small text-secondary">
                        Choose the site whose hotspot guests should be handled by this HotFii device.
                    </p>

                    <div class="list-group">
                        @foreach(session('unifi_sites') as $site)
                            <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $site['name'] }}</div>
                                    <div class="small text-secondary">
                                        Site <code>{{ $site['site_id'] }}</code>
                                    </div>
                                </div>

                                <form method="POST"
                                      action="{{ route('network.devices.unifi.site', $device) }}">
                                    @csrf
                                    <input type="hidden" name="site_id" value="{{ $site['site_id'] }}">
                                    <input type="hidden" name="host_id" value="{{ $site['host_id'] }}">

                                    <button class="btn btn-sm btn-outline-primary" type="submit">
                                        Use this site
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @endif


        @if($device->vendor === \App\Domain\Enums\RouterVendor::Omada)
            @php($omadaConfig = $device->management_config ?? [])

            <div class="card metric-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Omada Controller Connection</h2>

                    @if(
                        filled($omadaConfig['radius_source_ip'] ?? null)
                        && filled($omadaConfig['portal_host'] ?? null)
                    )
                        <span class="badge text-bg-success">
                            Configured
                        </span>
                    @else
                        <span class="badge text-bg-warning">
                            Setup required
                        </span>
                    @endif
                </div>

                <div class="card-body">

                    @if($errors->has('omada'))
                        <div class="alert alert-danger">
                            {{ $errors->first('omada') }}
                        </div>
                    @endif

                    <p class="text-secondary">
                        Enter the network details HotFii needs to identify
                        your Omada RADIUS traffic and return authenticated
                        guests to the Omada Controller.
                    </p>

                    <form method="POST"
                          action="{{ route('network.devices.omada.setup', $device) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">
                                RADIUS Source Public IP
                            </label>

                            <input
                                name="radius_source_ip"
                                class="form-control font-monospace"
                                value="{{ old('radius_source_ip', $omadaConfig['radius_source_ip'] ?? '') }}"
                                placeholder="e.g. 197.210.53.20"
                                required
                            >

                            <div class="form-text">
                                Public IPv4 address from which your Omada
                                Controller/Gateway reaches HotFii.
                            </div>
                        </div>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">
                                    Controller Portal Host
                                </label>

                                <input
                                    name="portal_host"
                                    class="form-control font-monospace"
                                    value="{{ old('portal_host', $omadaConfig['portal_host'] ?? '') }}"
                                    placeholder="controller.example.com"
                                    required
                                >

                                <div class="form-text">
                                    Do not include http://, https:// or port.
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">
                                    Scheme
                                </label>

                                @php($scheme = old(
                                    'portal_scheme',
                                    $omadaConfig['portal_scheme'] ?? 'https'
                                ))

                                <select
                                    name="portal_scheme"
                                    class="form-select"
                                    required
                                >
                                    <option value="https" @selected($scheme === 'https')>
                                        HTTPS
                                    </option>

                                    <option value="http" @selected($scheme === 'http')>
                                        HTTP
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">
                                    Portal Port
                                </label>

                                <input
                                    type="number"
                                    name="portal_port"
                                    min="1"
                                    max="65535"
                                    class="form-control"
                                    value="{{ old('portal_port', $omadaConfig['portal_port'] ?? 8843) }}"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    CoA / Disconnect Public IP
                                </label>

                                <input
                                    name="coa_host"
                                    class="form-control font-monospace"
                                    value="{{ old('coa_host', $omadaConfig['coa_host'] ?? '') }}"
                                    placeholder="Optional — defaults to RADIUS source IP"
                                >

                                <div class="form-text">
                                    HotFii sends RADIUS Disconnect-Requests
                                    here on port {{ config('hotfii.radius.coa_port') }}.
                                </div>
                            </div>
                        </div>

                        <button
                            class="btn btn-hotfii mt-4"
                            type="submit"
                        >
                            <i class="bi bi-router me-1"></i>
                            Save Omada settings
                        </button>
                    </form>

                    @if(
                        filled($omadaConfig['portal_host'] ?? null)
                        && filled($omadaConfig['portal_port'] ?? null)
                    )
                        <hr>

                        <div class="small text-secondary">
                            Omada Browser Authentication Endpoint
                        </div>

                        <code class="text-break">
                            {{ ($omadaConfig['portal_scheme'] ?? 'https').'://'.$omadaConfig['portal_host'].':'.$omadaConfig['portal_port'].'/portal/radius/browserauth' }}
                        </code>
                    @endif
                </div>
            </div>
        @endif

        <div class="card metric-card">
            <div class="card-header"><h2 class="h5 mb-0">{{ $provisioning['method'] === 'script'
    ? 'RouterOS provisioning script'
    : (($provisioning['integration'] ?? null) === 'unifi-network-api'
        ? 'UniFi Hotspot configuration'
        : 'Guided RADIUS configuration') }}</h2></div>
            <div class="card-body">
                @if($provisioning['method'] === 'script')
                    <div class="alert alert-warning"><i class="bi bi-shield-lock me-2"></i>This script contains the RADIUS secret. Run it only on the intended RouterOS 7 device.</div>
                    <div class="code-block">
                        <button class="btn-copy" type="button" data-copy-target="#provisioning-script" data-copy-message="Provisioning script copied"><i class="bi bi-clipboard me-1"></i><span data-copy-label>Copy script</span></button>
                        <pre class="provisioning-script"><code id="provisioning-script">{{ $provisioning['script'] }}</code></pre>
                    </div>
                    <p class="small text-secondary mt-2 mb-0">Paste the whole block into <span class="font-monospace">New Terminal</span> in Winbox or WebFig.</p>

                @elseif(($provisioning['integration'] ?? null) === 'unifi-network-api')

                    <div class="alert alert-info">
                        HotFii uses UniFi's External Portal and Network API.
                        No RouterOS-style script is required.
                    </div>

                    <label class="small text-secondary">External Portal URL</label>
                    <div class="input-group mb-4">
                        <div id="unifi-portal-url"
                             class="form-control bg-light font-monospace text-break">{{ $provisioning['external_portal_url'] }}</div>

                        <button class="btn btn-outline-secondary btn-copy-inline"
                                type="button"
                                data-copy-target="#unifi-portal-url"
                                data-copy-message="External portal URL copied">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>

                    <h3 class="h6">UniFi Network configuration</h3>

                    <ol class="mb-0">
                        <li>Open UniFi Network and create or select the guest Wi-Fi.</li>
                        <li>Enable <strong>Hotspot Portal / Captive Portal</strong>.</li>
                        <li>Select <strong>External Portal Server</strong>.</li>
                        <li>Use the HotFii External Portal URL shown above.</li>
                        <li>Allow <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code> before authentication if UniFi requests a pre-authorization domain.</li>
                    </ol>

                    <div class="small text-secondary mt-3">
                        HotFii handles voucher/payment authentication, guest authorization,
                        limits, session tracking and disconnect through the UniFi API.
                    </div>

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