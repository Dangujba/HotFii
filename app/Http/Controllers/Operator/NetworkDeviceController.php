<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Jobs\RunNetworkDeviceTests;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Network\RouterAdapterRegistry;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NetworkDeviceController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', ListFilters::enumValues(NetworkDeviceStatus::class)),
            'vendor' => ListFilters::choice($request, 'vendor', ListFilters::enumValues(RouterVendor::class)),
            'location' => ListFilters::id($request, 'location'),
        ];

        return view('network.index', [
            'devices' => $organization->networkDevices()
                ->with('location')
                ->when($filters['search'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('model', 'like', "%{$term}%")
                    ->orWhere('management_address', 'like', "%{$term}%")))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['vendor'], fn ($query, $vendor) => $query->where('vendor', $vendor))
                ->when($filters['location'], fn ($query, $location) => $query->where('location_id', $location))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'locations' => $organization->locations()->orderBy('name')->get(),
            'vendors' => RouterVendor::cases(),
            'statuses' => NetworkDeviceStatus::cases(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function store(Request $request, Organization $organization, NetworkDeviceManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['required', 'in:'.implode(',', array_column(RouterVendor::cases(), 'value'))],
            'model' => ['nullable', 'string', 'max:255'],
            'management_address' => ['nullable', 'string', 'max:255'],
        ]);

        $location = $organization->locations()->findOrFail($data['location_id']);
        $device = $manager->create($location, $data);

        return redirect()->route('network.devices.show', $device)
            ->with('success', 'Device created. Follow the provisioning instructions, then run the readiness test.');
    }

    public function show(Request $request, NetworkDevice $device, RouterAdapterRegistry $registry): View
    {
        $this->assertTenant($request, $device);

        $latestRun = $device->tests()->latest('id')->value('run_uuid');
        $tests = $latestRun
            ? $device->tests()->where('run_uuid', $latestRun)->orderBy('id')->get()
            : collect();

        return view('network.show', [
            'device' => $device->load('location'),
            'tests' => $tests,
            'provisioning' => $registry->byKey($device->adapter)->provisioning($device),
        ]);
    }

    public function test(Request $request, NetworkDevice $device): RedirectResponse
    {
        $this->assertTenant($request, $device);
        RunNetworkDeviceTests::dispatch($device);

        return back()->with('success', 'Readiness tests queued. Pending checks complete as router traffic arrives.');
    }

    private function assertTenant(Request $request, NetworkDevice $device): void
    {
        abort_unless($device->organization_id === $request->attributes->get('organization')->id, 404);
    }
}