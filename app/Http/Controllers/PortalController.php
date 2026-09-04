<?php

namespace App\Http\Controllers;

use App\Models\NetworkDevice;
use App\Services\Access\AllowanceService;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Vouchers\VoucherService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Hides the card-payment tab entirely when there is no settlement
            // account, rather than letting a customer pay into nowhere.
            'canBuyOnline' => $device->organization->canCollectLivePayments(),
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

    public function mikrotikLogin(NetworkDevice $device): \Illuminate\Http\Response
    {
        $portalUrl = route('portal.show', $device);

        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connecting to HotFii</title>
</head>
<body>
    <p>Connecting to HotFii…</p>

    <form id="hotfii-redirect" method="get" action="__PORTAL_URL__">
        <input type="hidden" name="link_login" value="$(link-login-only)">
        <input type="hidden" name="link_orig" value="$(link-orig)">
        <input type="hidden" name="mac" value="$(mac)">
        <input type="hidden" name="ip" value="$(ip)">
        <noscript>
            <button type="submit">Continue to HotFii</button>
        </noscript>
    </form>

    <script>
        document.getElementById('hotfii-redirect').submit();
    </script>
</body>
</html>
HTML;

        $html = str_replace(
            '__PORTAL_URL__',
            htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8'),
            $html
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function connected(NetworkDevice $device, Request $request)
    {
        $voucher = $device->organization->vouchers()
            ->with('credential')
            ->where('uuid', $request->query('voucher'))
            ->first();

        $config = $device->management_config ?? [];
        $nasIp = is_array($config)
            ? ($config['wireguard_address'] ?? null)
            : null;

        if ($nasIp) {
            $nasIp = preg_replace('/\/\d+$/', '', $nasIp);
        }

        if (! $nasIp) {
            $nasIp = DB::table('nas')
                ->where('network_device_id', $device->id)
                ->value('nasname');
        }

        $mac = $request->query('mac');

        $connected = false;

        if ($voucher?->credential && $nasIp) {
            $query = DB::table('radacct')
                ->where('username', $voucher->credential->username)
                ->where('nasipaddress', $nasIp)
                ->whereNull('acctstoptime')
                ->whereRaw("(
                    acctstarttime >= CURRENT_TIMESTAMP - INTERVAL '3 minutes'
                    OR acctupdatetime >= CURRENT_TIMESTAMP - INTERVAL '3 minutes'
                )");

            if ($mac) {
                $query->where('callingstationid', $mac);
            }

            $connected = $query->exists();
        }

        if ($request->boolean('check')) {
            return response()->json([
                'connected' => $connected,
            ]);
        }

        $originalUrl = $request->query('orig');

        if (! is_string($originalUrl)
            || ! preg_match('#^https?://#i', $originalUrl)) {
            $originalUrl = 'https://www.google.com';
        }

        return view('portal.connected', [
            'device' => $device,
            'voucher' => $voucher,
            'connected' => $connected,
            'originalUrl' => $originalUrl,
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