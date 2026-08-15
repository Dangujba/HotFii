<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\AccessGroup;
use App\Models\Customer;
use App\Models\Organization;
use App\Services\Radius\AccessGroupRadiusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessGroupController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.access-groups', [
            'groups' => $organization->accessGroups()->withCount('customers')->orderBy('name')->get(),
            'customers' => $organization->customers()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'days' => ['nullable', 'array'],
            'days.*' => ['in:Mo,Tu,We,Th,Fr,Sa,Su'],
            'start' => ['nullable', 'date_format:H:i'],
            'end' => ['nullable', 'date_format:H:i', 'after:start'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1'],
            'download_kbps' => ['nullable', 'integer', 'min:64'],
            'upload_kbps' => ['nullable', 'integer', 'min:64'],
            'device_limit' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $organization->accessGroups()->create([
            'name' => $data['name'],
            'schedule' => ['days' => $data['days'] ?? [], 'start' => $data['start'] ?? null, 'end' => $data['end'] ?? null],
            'data_limit_bytes' => isset($data['data_limit_mb']) ? $data['data_limit_mb'] * 1048576 : null,
            'download_kbps' => $data['download_kbps'] ?? null,
            'upload_kbps' => $data['upload_kbps'] ?? null,
            'device_limit' => $data['device_limit'],
        ]);

        return back()->with('success', 'Access group created.');
    }

    public function assign(
        Request $request,
        AccessGroup $group,
        Organization $organization,
        AccessGroupRadiusService $radius,
    ): RedirectResponse {
        abort_unless($group->organization_id === $organization->id, 404);
        $data = $request->validate(['customer_id' => ['required', 'integer']]);
        $customer = $organization->customers()->findOrFail($data['customer_id']);

        $group->customers()->syncWithoutDetaching([$customer->id]);
        $radius->synchronize($customer, $group);

        return back()->with('success', 'Identity assigned and active RADIUS rules synchronized.');
    }
}