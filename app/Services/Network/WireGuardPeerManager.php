<?php

namespace App\Services\Network;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WireGuardPeerManager
{
    public function enroll(string $publicKey, string $address): array
    {
        $url = rtrim((string) config('hotfii.wireguard.agent_url'), '/');
        $secret = (string) config('hotfii.wireguard.agent_secret');

        if ($url === '' || $secret === '') {
            throw new RuntimeException('WireGuard agent is not configured.');
        }

        $response = Http::timeout(8)
            ->acceptJson()
            ->withHeaders([
                'X-HotFii-Agent-Secret' => $secret,
            ])
            ->post($url.'/v1/peers/enroll', [
                'public_key' => $publicKey,
                'address' => $address,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'WireGuard peer enrollment failed: '.$response->status().' '.$response->body()
            );
        }

        return $response->json();
    }
}
