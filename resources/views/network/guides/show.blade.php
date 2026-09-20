@extends('layouts.app')

@section('content')

<style>
.guide-step {
    border-left: 4px solid var(--bs-primary);
    padding-left: 1.25rem;
    margin-bottom: 2rem;
}

.guide-code {
    font-family: monospace;
    word-break: break-all;
}

@media print {
    .guide-actions,
    nav,
    header,
    footer {
        display: none !important;
    }

    .card {
        break-inside: avoid;
    }
}
</style>

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <a
                href="{{ route('network.devices.show', $device) }}"
                class="text-decoration-none small"
            >
                ← Back to {{ $device->name }}
            </a>

            <h1 class="h3 mb-1 mt-2">
                {{ $device->vendor->label() }} Setup Guide
            </h1>

            <p class="text-secondary mb-0">
                {{ $device->name }} · {{ $device->location->name }}
            </p>
        </div>

        <div class="guide-actions d-flex flex-wrap gap-2">

            <div class="btn-group">

                <a
                    href="{{ route('network.devices.guide', [
                        'device' => $device,
                        'lang' => 'en',
                    ]) }}"
                    class="btn {{ $lang === 'en' ? 'btn-hotfii' : 'btn-outline-secondary' }}"
                >
                    English
                </a>

                <a
                    href="{{ route('network.devices.guide', [
                        'device' => $device,
                        'lang' => 'ha',
                    ]) }}"
                    class="btn {{ $lang === 'ha' ? 'btn-hotfii' : 'btn-outline-secondary' }}"
                >
                    Hausa
                </a>

            </div>

            <button
                type="button"
                class="btn btn-outline-secondary"
                onclick="window.print()"
            >
                <i class="bi bi-printer me-1"></i>
                {{ $lang === 'ha' ? 'Buga Jagora' : 'Print Guide' }}
            </button>

        </div>

    </div>


    <div class="card mb-4">

        <div class="card-header">
            <strong>
                {{ $lang === 'ha'
                    ? 'Bayanan Wannan Na’ura'
                    : 'This Device’s HotFii Details'
                }}
            </strong>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <tbody>

                    <tr>
                        <th style="width: 230px">
                            {{ $lang === 'ha' ? 'Sunan Na’ura' : 'Device Name' }}
                        </th>
                        <td>{{ $device->name }}</td>
                    </tr>

                    <tr>
                        <th>
                            {{ $lang === 'ha' ? 'Nau’in Na’ura' : 'Platform' }}
                        </th>
                        <td>{{ $device->vendor->label() }}</td>
                    </tr>

                    <tr>
                        <th>NAS ID</th>
                        <td>
                            <code>{{ $device->nas_identifier }}</code>
                        </td>
                    </tr>

                    <tr>
                        <th>RADIUS Server</th>
                        <td>
                            <code>{{ $radiusHost ?: 'HotFii server address' }}</code>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            {{ $lang === 'ha'
                                ? 'RADIUS Authentication Port'
                                : 'RADIUS Authentication Port'
                            }}
                        </th>
                        <td><code>{{ $authPort }}</code></td>
                    </tr>

                    <tr>
                        <th>RADIUS Accounting Port</th>
                        <td><code>{{ $accountingPort }}</code></td>
                    </tr>

                    <tr>
                        <th>CoA / Disconnect Port</th>
                        <td><code>{{ $coaPort }}</code></td>
                    </tr>

                    <tr>
                        <th>HotFii Portal URL</th>
                        <td>
                            <code class="guide-code">
                                {{ $portalUrl }}
                            </code>
                        </td>
                    </tr>

                    </tbody>

                </table>

            </div>

            <details class="mt-4">

                <summary class="fw-semibold">
                    {{
                        $lang === 'ha'
                            ? 'Nuna RADIUS Secret'
                            : 'Show RADIUS Secret'
                    }}
                </summary>

                <div class="alert alert-warning mt-3 mb-0">

                    <div class="small mb-2">
                        {{
                            $lang === 'ha'
                                ? 'Wannan sirrin na wannan na’ura ne kawai. Kada a raba shi da wani.'
                                : 'This secret belongs only to this device. Do not share it or reuse it on another router.'
                        }}
                    </div>

                    <code class="guide-code">
                        {{ $device->radius_secret }}
                    </code>

                </div>

            </details>

        </div>

    </div>


    @include($guideView)


    <div class="alert alert-secondary mt-5">

        <strong>
            {{ $lang === 'ha' ? 'Ka tuna:' : 'Remember:' }}
        </strong>

        {{
            $lang === 'ha'
                ? 'Kada a sake amfani da NAS ID, RADIUS Secret ko provisioning script na wata na’ura.'
                : 'Never reuse another device’s NAS ID, RADIUS secret or provisioning script.'
        }}

    </div>

</div>

@endsection
