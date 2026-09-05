<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OmadaSetupController extends Controller
{
    public function update(
        Request $request,
        NetworkDevice $device
    ): RedirectResponse {
        abort_unless(
            $device->organization_id ===
                $request->attributes->get('organization')->id,
            404
        );

        abort_unless(
            $device->vendor === RouterVendor::Omada,
            422
        );

        $data = $request->validate([
            /*
             * Public IPv4 address from which Omada Controller/Gateway
             * reaches HotFii RADIUS.
             */
            'radius_source_ip' => [
                'required',
                'ipv4',
            ],

            /*
             * Host/IP the CLIENT browser can reach for browserauth.
             * Do not include scheme or port here.
             */
            'portal_host' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9.-]+$/',
            ],

            'portal_scheme' => [
                'required',
                'in:http,https',
            ],

            'portal_port' => [
                'required',
                'integer',
                'between:1,65535',
            ],

            /*
             * Address HotFii should use for Disconnect-Request.
             * Usually the same WAN/public address.
             */
            'coa_host' => [
                'nullable',
                'ipv4',
            ],
        ]);

        $duplicateNas = DB::table('nas')
            ->where('nasname', $data['radius_source_ip'])
            ->where('network_device_id', '!=', $device->id)
            ->exists();

        if ($duplicateNas) {
            return back()->withErrors([
                'omada' =>
                    'That RADIUS source IP is already assigned to another HotFii device.',
            ])->withInput();
        }

        $config = $device->management_config ?? [];

        $config['radius_source_ip'] =
            $data['radius_source_ip'];

        $config['portal_host'] =
            strtolower($data['portal_host']);

        $config['portal_scheme'] =
            $data['portal_scheme'];

        $config['portal_port'] =
            (int) $data['portal_port'];

        $config['coa_host'] =
            $data['coa_host'] ?: $data['radius_source_ip'];

        $config['configured_at'] =
            now()->toIso8601String();

        DB::transaction(function () use (
            $device,
            $config,
            $data
        ) {
            $device->update([
                'management_config' => $config,

                // Generic disconnect job can continue using this field.
                'management_address' =>
                    $config['coa_host'],
            ]);

            /*
             * Dynamic FreeRADIUS discovery identifies the NAS by
             * the actual packet source IP.
             */
            DB::table('nas')
                ->where('network_device_id', $device->id)
                ->update([
                    'nasname' =>
                        $data['radius_source_ip'],

                    'shortname' =>
                        $device->nas_identifier,

                    'secret' =>
                        $device->radius_secret,

                    'type' => 'omada',

                    'description' =>
                        'HotFii TP-Link Omada',
                ]);
        });

        return back()->with(
            'success',
            'Omada controller connection settings saved.'
        );
    }
}
