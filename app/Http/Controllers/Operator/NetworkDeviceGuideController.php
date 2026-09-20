<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NetworkDeviceGuideController extends Controller
{
    public function show(
        Request $request,
        NetworkDevice $device
    ): View {
        $organization =
            $request->attributes->get('organization');

        abort_unless(
            $organization
            && $device->organization_id === $organization->id,
            404
        );

        $lang = $request->query('lang', 'en');

        if (! in_array($lang, ['en', 'ha'], true)) {
            $lang = 'en';
        }

        $vendorFolder = match ($device->vendor) {
            RouterVendor::Mikrotik => 'mikrotik',
            RouterVendor::Unifi => 'unifi',
            RouterVendor::Omada => 'omada',
            RouterVendor::Openwrt => 'openwrt',
            default => 'generic',
        };

        $guideView =
            "network.guides.{$vendorFolder}.{$lang}";

        abort_unless(
            view()->exists($guideView),
            404
        );

        return view('network.guides.show', [
            'device' => $device->load(
                'organization',
                'location'
            ),

            'lang' => $lang,
            'guideView' => $guideView,

            'radiusHost' =>
                config('hotfii.radius.host'),

            'authPort' =>
                config('hotfii.radius.auth_port', 1812),

            'accountingPort' =>
                config('hotfii.radius.accounting_port', 1813),

            'coaPort' =>
                config('hotfii.radius.coa_port', 3799),

            'portalUrl' =>
                route('portal.show', $device),

            'managementConfig' =>
                $device->management_config ?? [],
        ]);
    }
}
