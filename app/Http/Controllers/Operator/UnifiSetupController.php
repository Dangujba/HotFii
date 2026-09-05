<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\RouterVendor;
use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Services\Network\UnifiNetworkApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class UnifiSetupController extends Controller
{
    private function authorizeDevice(
        Request $request,
        NetworkDevice $device
    ): void {
        abort_unless(
            $device->organization_id ===
                $request->attributes->get('organization')->id,
            404
        );

        abort_unless(
            $device->vendor === RouterVendor::Unifi,
            422
        );
    }

    private function accountSiteName(array $site): string
    {
        return data_get($site, 'meta.desc')
            ?: data_get($site, 'meta.name')
            ?: ($site['name'] ?? 'UniFi Site');
    }

    private function resolveNetworkSite(
        array $localSites,
        array $accountSite
    ): ?array {
        $accountSiteId =
            $accountSite['siteId'] ?? null;

        /*
         * First try an exact identifier match in case UniFi eventually
         * returns the same ID through both APIs.
         */
        if ($accountSiteId) {
            $exact = collect($localSites)->first(
                fn ($site) =>
                    ($site['id'] ?? null) === $accountSiteId
                    || ($site['siteId'] ?? null) === $accountSiteId
            );

            if ($exact) {
                return $exact;
            }
        }

        /*
         * If Site Manager exposes an internal reference, prefer that.
         */
        $accountInternalReference =
            data_get($accountSite, 'meta.internalReference')
            ?: ($accountSite['internalReference'] ?? null);

        if ($accountInternalReference) {
            $reference = collect($localSites)->first(
                fn ($site) =>
                    Str::lower(
                        trim(
                            (string) (
                                $site['internalReference']
                                ?? ''
                            )
                        )
                    )
                    ===
                    Str::lower(
                        trim(
                            (string) $accountInternalReference
                        )
                    )
            );

            if ($reference) {
                return $reference;
            }
        }

        /*
         * Current UniFi Site Manager and Network Integration APIs expose
         * different site identifiers. Match the corresponding local site
         * by its human-readable name when necessary.
         */
        $name = Str::lower(
            trim(
                $this->accountSiteName($accountSite)
            )
        );

        $nameMatches = collect($localSites)
            ->filter(
                fn ($site) =>
                    Str::lower(
                        trim(
                            (string) (
                                $site['name']
                                ?? ''
                            )
                        )
                    ) === $name
            )
            ->values();

        if ($nameMatches->count() === 1) {
            return $nameMatches->first();
        }

        /*
         * A console containing exactly one Network site is unambiguous.
         */
        if (count($localSites) === 1) {
            return $localSites[0];
        }

        return null;
    }

    public function discover(
        Request $request,
        NetworkDevice $device,
        UnifiNetworkApi $api
    ): RedirectResponse {
        $this->authorizeDevice(
            $request,
            $device
        );

        $data = $request->validate([
            'api_key' => [
                'required',
                'string',
                'max:512',
            ],
        ]);

        try {
            $sites = $api->accountSites(
                $data['api_key']
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'unifi_api' =>
                    'HotFii could not connect to UniFi. Check the API key and try again.',
            ]);
        }

        if (empty($sites)) {
            return back()->withErrors([
                'unifi_api' =>
                    'The API key is valid, but no UniFi Network sites were found.',
            ]);
        }

        $config =
            $device->management_config ?? [];

        $config['api_key'] =
            $data['api_key'];

        $config['api_connected_at'] =
            now()->toIso8601String();

        $device->update([
            'management_config' => $config,
        ]);

        $choices = collect($sites)
            ->map(function ($site) {
                return [
                    'site_id' =>
                        $site['siteId'] ?? null,

                    'host_id' =>
                        $site['hostId'] ?? null,

                    'name' =>
                        $this->accountSiteName(
                            $site
                        ),
                ];
            })
            ->filter(
                fn ($site) =>
                    filled($site['site_id'])
                    && filled($site['host_id'])
            )
            ->values()
            ->all();

        return back()
            ->with(
                'unifi_sites',
                $choices
            )
            ->with(
                'success',
                'UniFi connected. Select the site HotFii should manage.'
            );
    }

    public function selectSite(
        Request $request,
        NetworkDevice $device,
        UnifiNetworkApi $api
    ): RedirectResponse {
        $this->authorizeDevice(
            $request,
            $device
        );

        $data = $request->validate([
            'site_id' => [
                'required',
                'string',
                'max:255',
            ],

            'host_id' => [
                'required',
                'string',
                'max:512',
            ],
        ]);

        $config =
            $device->management_config ?? [];

        $apiKey =
            $config['api_key'] ?? null;

        abort_unless(
            filled($apiKey),
            422,
            'Configure the UniFi API key first.'
        );

        try {
            $accountSites =
                $api->accountSites($apiKey);

            $accountSite =
                collect($accountSites)->first(
                    fn ($site) =>
                        ($site['siteId'] ?? null)
                            === $data['site_id']
                        &&
                        ($site['hostId'] ?? null)
                            === $data['host_id']
                );

            if (! $accountSite) {
                return back()->withErrors([
                    'unifi_api' =>
                        'The selected UniFi site is not accessible with this API key.',
                ]);
            }

            $localSites =
                $api->localSites(
                    $apiKey,
                    $data['host_id']
                );

            $networkSite =
                $this->resolveNetworkSite(
                    $localSites,
                    $accountSite
                );

            $networkSiteId =
                $networkSite['id']
                ?? $networkSite['siteId']
                ?? null;

            if (! $networkSite || ! $networkSiteId) {
                return back()->withErrors([
                    'unifi_api' =>
                        'HotFii connected to the UniFi console but could not map the selected Site Manager site to a UniFi Network site.',
                ]);
            }

        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'unifi_api' =>
                    'HotFii could not verify the selected UniFi site.',
            ]);
        }

        /*
         * Keep both identifiers:
         *
         * site_manager_site_id:
         *   Returned by https://api.ui.com/v1/sites
         *
         * network_site_id:
         *   Returned by the UniFi Network Integration API and required
         *   for clients, authorization, session sync and disconnect.
         *
         * Keep site_id as the Site Manager value for backward compatibility
         * with existing HotFii UI/data.
         */
        $config['site_id'] =
            $data['site_id'];

        $config['site_manager_site_id'] =
            $data['site_id'];

        $config['network_site_id'] =
            (string) $networkSiteId;

        $config['network_site_internal_reference'] =
            $networkSite['internalReference']
            ?? null;

        $config['host_id'] =
            $data['host_id'];

        $config['site_name'] =
            $this->accountSiteName(
                $accountSite
            );

        $config['configured_at'] =
            now()->toIso8601String();

        $device->update([
            'management_config' => $config,
        ]);

        return back()->with(
            'success',
            'UniFi site connected successfully. HotFii mapped the Site Manager site to the UniFi Network site.'
        );
    }
}
