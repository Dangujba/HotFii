<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Services\Network\UnifiNetworkApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class UnifiSetupController extends Controller
{
    private function authorizeDevice(Request $request, NetworkDevice $device): void
    {
        abort_unless(
            $device->organization_id === $request->attributes->get('organization')->id,
            404
        );

        abort_unless($device->vendor === RouterVendor::Unifi, 422);
    }

    public function discover(
        Request $request,
        NetworkDevice $device,
        UnifiNetworkApi $api
    ): RedirectResponse {
        $this->authorizeDevice($request, $device);

        $data = $request->validate([
            'api_key' => ['required', 'string', 'max:512'],
        ]);

        try {
            $sites = $api->accountSites($data['api_key']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'unifi_api' => 'HotFii could not connect to UniFi. Check the API key and try again.',
            ]);
        }

        if (empty($sites)) {
            return back()->withErrors([
                'unifi_api' => 'The API key is valid, but no UniFi Network sites were found.',
            ]);
        }

        $config = $device->management_config ?? [];

        $config['api_key'] = $data['api_key'];
        $config['api_connected_at'] = now()->toIso8601String();

        $device->update([
            'management_config' => $config,
        ]);

        $choices = collect($sites)->map(function ($site) {
            return [
                'site_id' => $site['siteId'] ?? null,
                'host_id' => $site['hostId'] ?? null,
                'name' => data_get($site, 'meta.desc')
                    ?: data_get($site, 'meta.name')
                    ?: 'UniFi Site',
            ];
        })->filter(
            fn ($site) => filled($site['site_id']) && filled($site['host_id'])
        )->values()->all();

        return back()
            ->with('unifi_sites', $choices)
            ->with('success', 'UniFi connected. Select the site HotFii should manage.');
    }

    public function selectSite(
        Request $request,
        NetworkDevice $device,
        UnifiNetworkApi $api
    ): RedirectResponse {
        $this->authorizeDevice($request, $device);

        $data = $request->validate([
            'site_id' => ['required', 'string', 'max:255'],
            'host_id' => ['required', 'string', 'max:512'],
        ]);

        $config = $device->management_config ?? [];
        $apiKey = $config['api_key'] ?? null;

        abort_unless(filled($apiKey), 422, 'Configure the UniFi API key first.');

        try {
            $sites = $api->accountSites($apiKey);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'unifi_api' => 'HotFii could not verify the selected UniFi site.',
            ]);
        }

        $site = collect($sites)->first(
            fn ($site) =>
                ($site['siteId'] ?? null) === $data['site_id']
                && ($site['hostId'] ?? null) === $data['host_id']
        );

        if (! $site) {
            return back()->withErrors([
                'unifi_api' => 'The selected UniFi site is not accessible with this API key.',
            ]);
        }

        $config['site_id'] = $data['site_id'];
        $config['host_id'] = $data['host_id'];
        $config['site_name'] = data_get($site, 'meta.desc')
            ?: data_get($site, 'meta.name')
            ?: 'UniFi Site';
        $config['configured_at'] = now()->toIso8601String();

        $device->update([
            'management_config' => $config,
        ]);

        return back()->with(
            'success',
            'UniFi site connected successfully. HotFii can now communicate with this controller.'
        );
    }
}
