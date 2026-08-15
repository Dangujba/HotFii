<?php

namespace App\Http\Controllers;

use App\Models\NetworkDevice;
use App\Services\Access\AllowanceService;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Vouchers\VoucherService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PortalController extends Controller
{
    public function show(Request $request, NetworkDevice $device, NetworkDeviceManager $manager): View
    {
        if ($request->filled(['link_login', 'mac'])) {
            $manager->markEvidence(
                $device,
                'captive_portal',
                'A router redirect reached the HotFii captive portal.',
                ['mac' => $request->query('mac')],
            );
        }

        return view('portal.show', [
            'device' => $device->load('organization', 'location'),
            'plans' => $device->organization->accessPlans()
                ->where('is_active', true)
                ->orderBy('price_kobo')
                ->get(),
            'portalContext' => $request->only(['link_login', 'link_orig', 'mac', 'ip']),
        ]);
    }

    public function redeem(Request $request, NetworkDevice $device, VoucherService $service): RedirectResponse
    {
        $data = $request->validate([
            'voucher_code' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'link_login' => ['nullable', 'url:http,https', 'max:500'],
            'link_orig' => ['nullable', 'url:http,https', 'max:500'],
            'mac' => ['nullable', 'string', 'max:32'],
            'ip' => ['nullable', 'ip'],
        ]);

        try {
            $voucher = $service->redeem($device->organization, $data['voucher_code'], $data['phone'] ?? null);
        } catch (RuntimeException|ModelNotFoundException $exception) {
            return back()->withErrors([
                'voucher_code' => $exception instanceof ModelNotFoundException
                    ? 'The voucher code was not found.'
                    : $exception->getMessage(),
            ])->withInput();
        }

        return redirect()->route('portal.status', [
            'device' => $device,
            'voucher' => $voucher->uuid,
            ...collect($data)->only(['link_login', 'link_orig', 'mac', 'ip'])->filter()->all(),
        ]);
    }

    public function status(NetworkDevice $device, Request $request, AllowanceService $allowances): View
    {
        $voucher = $device->organization->vouchers()
            ->with('credential.accessPlan', 'batch.accessPlan')
            ->where('uuid', $request->query('voucher'))
            ->first();

        return view('portal.status', [
            'device' => $device,
            'voucher' => $voucher,
            'portalContext' => $request->only(['link_login', 'link_orig', 'mac', 'ip']),
            'allowance' => $allowances->forCredential($voucher?->credential),
        ]);
    }
}