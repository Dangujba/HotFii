<?php

namespace App\Jobs;

use App\Events\HotspotSessionUpdated;
use App\Models\HotspotSession;
use App\Services\Network\UnifiNetworkApi;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncUnifiSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('network');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('unifi-session-sync'))
                ->expireAfter(180),
        ];
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(UnifiNetworkApi $api): void
    {
        $sessions = HotspotSession::query()
            ->with('networkDevice')
            ->where('source', 'unifi')
            ->where('status', 'active')
            ->get();

        /*
         * UniFi Cloud Connector has per-console rate limits.
         * Process no more than 80 detailed client lookups per host per run,
         * prioritising sessions that were synchronized least recently.
         */
        $groups = $sessions->groupBy(function (HotspotSession $session) {
            return data_get(
                $session->networkDevice?->management_config,
                'host_id',
                'unconfigured'
            );
        });

        foreach ($groups as $hostId => $group) {
            if ($hostId === 'unconfigured') {
                continue;
            }

            $group = $group->sortBy(function (HotspotSession $session) {
                return data_get(
                    $session->session_meta,
                    'last_synced_at',
                    '1970-01-01T00:00:00Z'
                );
            })->take(80);

            foreach ($group as $session) {
                $this->syncOne($api, $session);
            }
        }
    }

    private function syncOne(
        UnifiNetworkApi $api,
        HotspotSession $session
    ): void {
        $device = $session->networkDevice;

        if (! $device) {
            return;
        }

        $config = $device->management_config ?? [];

        $apiKey = $config['api_key'] ?? null;
        $hostId = $config['host_id'] ?? null;
        $siteId = $config['network_site_id']
            ?? $config['site_id']
            ?? null;

        $clientId = $session->external_session_id;

        if (! $apiKey || ! $hostId || ! $siteId || ! $clientId) {
            return;
        }

        try {
            $client = $api->clientDetails(
                $apiKey,
                $hostId,
                $siteId,
                $clientId,
            );
        } catch (Throwable $exception) {
            report($exception);
            return;
        }

        $meta = $session->session_meta ?? [];

        /*
         * A client can disappear briefly during roaming/reassociation.
         * Do not immediately close the HotFii session on the first 404.
         */
        if (! $client) {
            $missingSince = $meta['missing_since'] ?? null;

            if (! $missingSince) {
                $meta['missing_since'] = now()->toIso8601String();
                $meta['last_sync_attempt_at'] = now()->toIso8601String();

                $session->update(['session_meta' => $meta]);
                return;
            }

            if (Carbon::parse($missingSince)->gt(now()->subMinutes(2))) {
                return;
            }

            $session->update([
                'status' => 'stopped',
                'stopped_at' => now(),
                'terminate_cause' => 'Client-Disconnected',
                'session_meta' => [
                    ...$meta,
                    'last_synced_at' => now()->toIso8601String(),
                ],
            ]);

            HotspotSessionUpdated::dispatch($session->refresh());
            return;
        }

        unset($meta['missing_since']);

        $authorized = (bool) data_get(
            $client,
            'access.authorized',
            false
        );

        $authorization = data_get(
            $client,
            'access.authorization',
            []
        );

        $usage = data_get(
            $authorization,
            'usage',
            []
        );

        /*
         * UniFi rxBytes = data delivered to the client (download).
         * UniFi txBytes = data sent by the client (upload).
         * RADIUS acct input represents traffic entering the NAS from client,
         * so keep HotFii's input/output semantics consistent.
         */
        $inputBytes = (int) (
            $usage['txBytes']
            ?? $session->input_bytes
        );

        $outputBytes = (int) (
            $usage['rxBytes']
            ?? $session->output_bytes
        );

        $expiresAt = data_get(
            $authorization,
            'expiresAt'
        );

        $updates = [
            'ip_address' => $client['ipAddress']
                ?? $session->ip_address,

            'input_bytes' => $inputBytes,
            'output_bytes' => $outputBytes,

            'session_meta' => [
                ...$meta,
                'last_synced_at' => now()->toIso8601String(),
                'authorization' => $authorization,
            ],
        ];

        if ($expiresAt) {
            $updates['expires_at'] = Carbon::parse($expiresAt);
        }

        if (! $authorized) {
            $updates['status'] = 'stopped';
            $updates['stopped_at'] = now();
            $updates['terminate_cause'] = 'UniFi-Unauthorized';
        }

        $session->update($updates);

        HotspotSessionUpdated::dispatch($session->refresh());
    }
}
