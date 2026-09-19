@extends('layouts.platform')

@section('title', 'Routers')
@section('heading', 'Router Network')
@section(
    'subheading',
    'Every managed router across the HotFii platform'
)

@section('actions')
<a
    href="{{ route('platform.system.index') }}"
    class="btn btn-outline-secondary"
>
    <i class="bi bi-activity me-1"></i>
    System Health
</a>
@endsection

@section('content')

<div class="row g-3 mb-4">

    @foreach([
        ['Total routers', $stats['total'], 'router', 'primary'],
        ['Online', $stats['online'], 'wifi', 'success'],
        ['Offline', $stats['offline'], 'wifi-off', 'danger'],
        ['Testing', $stats['testing'], 'beaker', 'info'],
        ['Pending', $stats['pending'], 'hourglass-split', 'warning'],
        ['Failed', $stats['failed'], 'exclamation-triangle', 'danger'],
    ] as [$label, $value, $icon, $tone])

        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="text-secondary small">
                        {{ $label }}
                    </div>

                    <div class="fs-4 fw-bold">
                        {{ number_format($value) }}
                    </div>

                    <i class="bi bi-{{ $icon }} text-{{ $tone }}"></i>
                </div>
            </div>
        </div>

    @endforeach

</div>


<div class="card metric-card">

    <x-filter-bar
        :action="route('platform.routers.index')"
        :active="$filtered"
    >

        <div class="col-lg-3">
            <input
                class="form-control form-control-sm"
                name="search"
                value="{{ $filters['search'] }}"
                placeholder="Router, NAS, model or organization"
            >
        </div>

        <div class="col-lg-3">
            <select
                class="form-select form-select-sm"
                name="organization"
            >
                <option value="">
                    All organizations
                </option>

                @foreach($organizations as $organization)
                    <option
                        value="{{ $organization->id }}"
                        @selected(
                            (string) $organization->id
                            === $filters['organization']
                        )
                    >
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-lg-2">
            <select
                class="form-select form-select-sm"
                name="vendor"
            >
                <option value="">
                    All vendors
                </option>

                @foreach($vendors as $vendor)
                    <option
                        value="{{ $vendor->value }}"
                        @selected(
                            $filters['vendor']
                            === $vendor->value
                        )
                    >
                        {{ $vendor->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-lg-2">
            <select
                class="form-select form-select-sm"
                name="status"
            >
                <option value="">
                    Any status
                </option>

                @foreach($statuses as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected(
                            $filters['status']
                            === $status->value
                        )
                    >
                        {{ ucfirst($status->value) }}
                    </option>
                @endforeach
            </select>
        </div>

    </x-filter-bar>


    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>
                <tr>
                    <th>Router</th>
                    <th>Organization</th>
                    <th>Vendor</th>
                    <th>Status</th>
                    <th class="text-end">Live Users</th>
                    <th class="text-end">Data</th>
                    <th>Last Heartbeat</th>
                </tr>
                </thead>

                <tbody>

                @forelse($devices as $device)

                    @php
                        $statusTone = match(
                            $device->status->value
                        ) {
                            'online' => 'success',
                            'testing' => 'info',
                            'pending' => 'warning',
                            'offline' => 'secondary',
                            'failed' => 'danger',
                            default => 'secondary',
                        };

                        $dataBytes =
                            (int) (
                                $device->sessions_sum_input_bytes
                                ?? 0
                            )
                            +
                            (int) (
                                $device->sessions_sum_output_bytes
                                ?? 0
                            );
                    @endphp

                    <tr>

                        <td>
                            <strong>
                                {{ $device->name }}
                            </strong>

                            <div class="small text-secondary">

                                {{ $device->model ?: 'Model unknown' }}

                                @if($device->firmware_version)
                                    · {{ $device->firmware_version }}
                                @endif

                            </div>

                            <code class="small">
                                {{ $device->nas_identifier }}
                            </code>
                        </td>

                        <td>
                            <a
                                href="{{
                                    route(
                                        'platform.organizations.show',
                                        $device->organization
                                    )
                                }}"
                                class="text-decoration-none"
                            >
                                {{ $device->organization->name }}
                            </a>

                            @if($device->location)
                                <div class="small text-secondary">
                                    {{ $device->location->name }}
                                </div>
                            @endif
                        </td>

                        <td>
                            {{ $device->vendor->label() }}
                        </td>

                        <td>
                            <span
                                class="badge text-bg-{{ $statusTone }}"
                            >
                                {{
                                    ucfirst(
                                        $device->status->value
                                    )
                                }}
                            </span>
                        </td>

                        <td class="text-end">
                            <strong>
                                {{
                                    number_format(
                                        $device->active_sessions_count
                                    )
                                }}
                            </strong>
                        </td>

                        <td class="text-end">
                            {{
                                \App\Support\Bytes::human(
                                    $dataBytes
                                )
                            }}
                        </td>

                        <td>
                            @if($device->last_heartbeat_at)
                                {{
                                    $device
                                        ->last_heartbeat_at
                                        ->diffForHumans()
                                }}
                            @else
                                <span class="text-secondary">
                                    Never
                                </span>
                            @endif
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="7"
                            class="text-center py-5 text-secondary"
                        >
                            {{
                                $filtered
                                    ? 'No routers match these filters.'
                                    : 'No routers have been registered.'
                            }}
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

<div class="mt-3">
    {{ $devices->links() }}
</div>

@endsection
