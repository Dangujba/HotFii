<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Jobs\RunNetworkDeviceTests;
use App\Models\NetworkDevice;
use App\Models\NetworkDeviceTest;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileNetworkController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(NetworkDeviceStatus::class)],
            'vendor' => ['nullable', Rule::enum(RouterVendor::class)],
            'location' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $location = isset($data['location'])
            ? $organization->locations()->where('uuid', $data['location'])->firstOrFail()
            : null;
        $routers = $organization->networkDevices()
            ->with('location')
            ->withCount([
                'sessions',
                'sessions as active_sessions_count' => fn (Builder $query) => $query
                    ->whereIn('status', ['active', 'disconnect_pending']),
            ])
            ->when(trim((string) ($data['search'] ?? '')), fn (Builder $query, string $term) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('model', 'like', "%{$term}%")
                    ->orWhere('management_address', 'like', "%{$term}%")
                    ->orWhere('nas_identifier', 'like', "%{$term}%")))
            ->when($data['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($data['vendor'] ?? null, fn (Builder $query, string $vendor) => $query->where('vendor', $vendor))
            ->when($location, fn (Builder $query) => $query->where('location_id', $location->id))
            ->orderBy('name')
            ->paginate((int) ($data['per_page'] ?? 20));
        $all = $organization->networkDevices();

        return response()->json(['data' => [
            'summary' => [
                'total' => (clone $all)->count(),
                'online' => (clone $all)->where('status', NetworkDeviceStatus::Online->value)->count(),
                'offline' => (clone $all)->where('status', NetworkDeviceStatus::Offline->value)->count(),
                'attention' => (clone $all)->whereIn('status', [
                    NetworkDeviceStatus::Pending->value,
                    NetworkDeviceStatus::Testing->value,
                    NetworkDeviceStatus::Failed->value,
                ])->count(),
            ],
            'routers' => collect($routers->items())->map(fn (NetworkDevice $device) => $this->router($device))->values(),
            'pagination' => [
                'current_page' => $routers->currentPage(),
                'last_page' => $routers->lastPage(),
                'per_page' => $routers->perPage(),
                'total' => $routers->total(),
            ],
            'options' => [
                'statuses' => collect(NetworkDeviceStatus::cases())->map(fn ($status) => $status->value)->values(),
                'vendors' => collect(RouterVendor::cases())->map(fn (RouterVendor $vendor) => [
                    'value' => $vendor->value,
                    'label' => $vendor->label(),
                ])->values(),
                'locations' => $organization->locations()->orderBy('name')->get()->map(fn ($item) => [
                    'id' => $item->uuid,
                    'name' => $item->name,
                ])->values(),
            ],
            'permissions' => ['can_manage' => $this->canManage($request, $organization)],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Organization $organization, NetworkDevice $device): JsonResponse
    {
        abort_unless($device->organization_id === $organization->id, 404);
        $device->load('location')->loadCount([
            'sessions',
            'sessions as active_sessions_count' => fn (Builder $query) => $query
                ->whereIn('status', ['active', 'disconnect_pending']),
        ]);
        $latestRun = $device->tests()->latest('id')->value('run_uuid');
        $tests = $latestRun
            ? $device->tests()
                ->where('run_uuid', $latestRun)
                ->whereNotIn('test_key', ['coa', 'disconnect'])
                ->orderBy('id')
                ->get()
            : collect();

        return response()->json(['data' => [
            'router' => $this->router($device) + [
                'firmware_version' => $device->firmware_version,
                'management_address' => $device->management_address,
                'nas_identifier' => $device->nas_identifier,
                'capabilities' => array_values($device->capabilities ?? []),
                'health' => $this->safeHealth($device->health ?? []),
                'certified_at' => $device->certified_at?->toIso8601String(),
                'setup' => $this->setup($device),
            ],
            'latest_test_run' => $latestRun,
            'tests' => $tests->map(fn (NetworkDeviceTest $test) => [
                'key' => $test->test_key,
                'status' => $test->status,
                'message' => $test->message,
                'checked_at' => $test->checked_at?->toIso8601String(),
            ])->values(),
            'permissions' => ['can_manage' => $this->canManage($request, $organization)],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function test(Request $request, Organization $organization, NetworkDevice $device): JsonResponse
    {
        abort_unless($device->organization_id === $organization->id, 404);
        abort_unless($this->canManage($request, $organization), 403, 'Your role cannot run router readiness tests.');
        RunNetworkDeviceTests::dispatch($device);

        return response()->json(['message' => 'Readiness tests queued. Refresh shortly for confirmed results.'], 202)
            ->header('Cache-Control', 'no-store, private');
    }

    private function router(NetworkDevice $device): array
    {
        return [
            'id' => $device->uuid,
            'name' => $device->name,
            'vendor' => $device->vendor->value,
            'vendor_label' => $device->vendor->label(),
            'model' => $device->model,
            'adapter' => $device->adapter,
            'support_level' => $device->support_level->value,
            'status' => $device->status->value,
            'location_id' => $device->location?->uuid,
            'location_name' => $device->location?->name,
            'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            'sessions_count' => (int) ($device->sessions_count ?? 0),
            'active_sessions_count' => (int) ($device->active_sessions_count ?? 0),
        ];
    }

    private function setup(NetworkDevice $device): array
    {
        $config = $device->management_config ?? [];
        $configured = match ($device->vendor) {
            RouterVendor::Unifi => filled($config['site_id'] ?? null),
            RouterVendor::Omada => filled($config['radius_source_ip'] ?? null)
                && filled($config['portal_host'] ?? null),
            RouterVendor::Mikrotik, RouterVendor::Openwrt => $device->last_heartbeat_at !== null,
            default => $device->status === NetworkDeviceStatus::Online,
        };

        return [
            'configured' => $configured,
            'label' => $configured ? 'Configured' : 'Setup required',
        ];
    }

    private function safeHealth(array $health): array
    {
        return collect($health)
            ->filter(fn ($value, $key) => is_string($key) && (is_scalar($value) || $value === null))
            ->take(12)
            ->all();
    }

    private function canManage(Request $request, Organization $organization): bool
    {
        return $request->user()->is_platform_admin
            || in_array($request->user()->roleFor($organization), ['owner', 'manager', 'technician'], true);
    }
}
