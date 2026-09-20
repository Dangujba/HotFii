<?php

namespace App\Services\Network;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class UnifiNetworkApi
{
    private const BASE_URL = 'https://api.ui.com';

    private function client(string $apiKey): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-Key' => $apiKey,
            ])
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    private function networkPath(string $hostId, string $path): string
    {
        return '/v1/connector/consoles/'
            .rawurlencode($hostId)
            .'/network/integration/v1'
            .$path;
    }

    public function accountSites(string $apiKey): array
    {
        $sites = [];
        $nextToken = null;

        do {
            $query = ['pageSize' => 100];

            if ($nextToken) {
                $query['nextToken'] = $nextToken;
            }

            $response = $this->client($apiKey)
                ->get('/v1/sites', $query);

            $response->throw();

            $sites = array_merge(
                $sites,
                $response->json('data') ?? []
            );

            $nextToken = $response->json('nextToken');
        } while ($nextToken);

        return $sites;
    }

    public function localSites(string $apiKey, string $hostId): array
    {
        $response = $this->client($apiKey)->get(
            $this->networkPath($hostId, '/sites')
        );

        $response->throw();

        return $response->json('data')
            ?? $response->json()
            ?? [];
    }

    public function clients(
        string $apiKey,
        string $hostId,
        string $siteId,
        ?string $filter = null,
    ): array {
        $query = [
            'offset' => 0,
            'limit' => 200,
        ];

        if ($filter) {
            $query['filter'] = $filter;
        }

        $response = $this->client($apiKey)->get(
            $this->networkPath(
                $hostId,
                '/sites/'.rawurlencode($siteId).'/clients'
            ),
            $query,
        );

        $response->throw();

        return $response->json('data')
            ?? $response->json()
            ?? [];
    }

    public function clientByMac(
        string $apiKey,
        string $hostId,
        string $siteId,
        string $mac,
    ): ?array {
        $mac = strtoupper(trim($mac));

        $clients = $this->clients(
            $apiKey,
            $hostId,
            $siteId,
            "macAddress.eq('{$mac}')",
        );

        return $clients[0] ?? null;
    }

    public function clientDetails(
        string $apiKey,
        string $hostId,
        string $siteId,
        string $clientId,
    ): ?array {
        $response = $this->client($apiKey)->get(
            $this->networkPath(
                $hostId,
                '/sites/'.rawurlencode($siteId)
                .'/clients/'.rawurlencode($clientId)
            )
        );

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $response->json('data')
            ?? $response->json();
    }

    private function executeClientAction(
        string $apiKey,
        string $hostId,
        string $siteId,
        string $clientId,
        array $payload,
    ): array {
        $response = $this->client($apiKey)->post(
            $this->networkPath(
                $hostId,
                '/sites/'.rawurlencode($siteId)
                .'/clients/'.rawurlencode($clientId)
                .'/actions'
            ),
            $payload,
        );

        $response->throw();

        return $response->json('data')
            ?? $response->json()
            ?? [];
    }

    public function authorizeGuest(
        string $apiKey,
        string $hostId,
        string $siteId,
        string $clientId,
        array $limits = [],
    ): array {
        $payload = [
            'action' => 'AUTHORIZE_GUEST_ACCESS',
        ];

        if (($limits['time_minutes'] ?? null) !== null) {
            $payload['timeLimitMinutes'] = min(
                1000000,
                max(1, (int) $limits['time_minutes'])
            );
        }

        if (($limits['data_mb'] ?? null) !== null) {
            $payload['dataUsageLimitMBytes'] = min(
                1048576,
                max(1, (int) $limits['data_mb'])
            );
        }

        if (($limits['download_kbps'] ?? null) !== null) {
            $payload['rxRateLimitKbps'] = min(
                100000,
                max(2, (int) $limits['download_kbps'])
            );
        }

        if (($limits['upload_kbps'] ?? null) !== null) {
            $payload['txRateLimitKbps'] = min(
                100000,
                max(2, (int) $limits['upload_kbps'])
            );
        }

        return $this->executeClientAction(
            $apiKey,
            $hostId,
            $siteId,
            $clientId,
            $payload,
        );
    }

    public function unauthorizeGuest(
        string $apiKey,
        string $hostId,
        string $siteId,
        string $clientId,
    ): array {
        return $this->executeClientAction(
            $apiKey,
            $hostId,
            $siteId,
            $clientId,
            ['action' => 'UNAUTHORIZE_GUEST_ACCESS'],
        );
    }
}
