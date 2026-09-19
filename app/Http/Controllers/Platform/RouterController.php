<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RouterController extends Controller
{
    public function __invoke(Request $request): View
    {
        $allowedStatuses = array_map(
            fn (NetworkDeviceStatus $status) => $status->value,
            NetworkDeviceStatus::cases()
        );

        $allowedVendors = array_map(
            fn (RouterVendor $vendor) => $vendor->value,
            RouterVendor::cases()
        );

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
            'vendor' => (string) $request->query('vendor', ''),
            'organization' => (string) $request->query('organization', ''),
        ];

        if (
            $filters['status'] !== ''
            && ! in_array($filters['status'], $allowedStatuses, true)
        ) {
            $filters['status'] = '';
        }

        if (
            $filters['vendor'] !== ''
            && ! in_array($filters['vendor'], $allowedVendors, true)
        ) {
            $filters['vendor'] = '';
        }

        $organizationId =
            filter_var(
                $filters['organization'],
                FILTER_VALIDATE_INT
            ) ?: null;

        $query = NetworkDevice::query()
            ->with([
                'organization',
                'location',
            ])
            ->withCount([
                'sessions as active_sessions_count' =>
                    fn ($query) =>
                        $query->whereIn(
                            'status',
                            [
                                'active',
                                'disconnect_pending',
                            ]
                        ),
            ])
            ->withSum('sessions', 'input_bytes')
            ->withSum('sessions', 'output_bytes');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'name',
                        'ilike',
                        '%'.$search.'%'
                    )
                    ->orWhere(
                        'nas_identifier',
                        'ilike',
                        '%'.$search.'%'
                    )
                    ->orWhere(
                        'model',
                        'ilike',
                        '%'.$search.'%'
                    )
                    ->orWhereHas(
                        'organization',
                        fn ($organization) =>
                            $organization->where(
                                'name',
                                'ilike',
                                '%'.$search.'%'
                            )
                    );
            });
        }

        if ($filters['status'] !== '') {
            $query->where(
                'status',
                $filters['status']
            );
        }

        if ($filters['vendor'] !== '') {
            $query->where(
                'vendor',
                $filters['vendor']
            );
        }

        if ($organizationId) {
            $query->where(
                'organization_id',
                $organizationId
            );
        }

        $devices = $query
            ->orderByRaw("
                CASE status
                    WHEN 'online' THEN 0
                    WHEN 'testing' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'offline' THEN 3
                    WHEN 'failed' THEN 4
                    ELSE 5
                END
            ")
            ->orderByRaw(
                'last_heartbeat_at DESC NULLS LAST'
            )
            ->paginate(30)
            ->withQueryString();

        $statusCounts =
            NetworkDevice::query()
                ->selectRaw(
                    'status, COUNT(*) as total'
                )
                ->groupBy('status')
                ->pluck('total', 'status');

        return view('platform.routers.index', [
            'devices' => $devices,

            'filters' => $filters,

            'filtered' =>
                collect($filters)
                    ->contains(
                        fn ($value) =>
                            $value !== ''
                    ),

            'organizations' =>
                Organization::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

            'statuses' =>
                NetworkDeviceStatus::cases(),

            'vendors' =>
                RouterVendor::cases(),

            'stats' => [
                'total' =>
                    NetworkDevice::count(),

                'online' =>
                    (int) (
                        $statusCounts['online']
                        ?? 0
                    ),

                'offline' =>
                    (int) (
                        $statusCounts['offline']
                        ?? 0
                    ),

                'testing' =>
                    (int) (
                        $statusCounts['testing']
                        ?? 0
                    ),

                'pending' =>
                    (int) (
                        $statusCounts['pending']
                        ?? 0
                    ),

                'failed' =>
                    (int) (
                        $statusCounts['failed']
                        ?? 0
                    ),
            ],
        ]);
    }
}
