<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Jobs\RunNetworkDeviceTests;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Network\RouterAdapterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NetworkDeviceController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('network.index', [
            'devices' => $organization->networkDevices()->with('location')->latest()->paginate(20),
            'locations' => $organization->locations()->orderBy('name')->get(),
            'vendors' => RouterVendor::cases(),
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