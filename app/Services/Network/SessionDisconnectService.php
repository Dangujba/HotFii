<?php

namespace App\Services\Network;

use App\Events\HotspotSessionUpdated;
use App\Models\HotspotSession;
use RuntimeException;
use Throwable;

final class SessionDisconnectService
{
    public function __construct(
        private readonly RouterAdapterRegistry $adapters,
        private readonly NetworkDeviceManager $devices,
    ) {}

    public function disconnect(HotspotSession $session): HotspotSession
    {
        $session = $session->refresh();
        if ($session->status === 'stopped' && $session->terminate_cause === 'Admin-Reset') {
            return $session;
        }
        if (! in_array($session->status, ['active', 'disconnect_pending'], true)) {
            throw new RuntimeException('This session is not active.');
        }

        if ($session->status === 'active') {
            $session->update(['status' => 'disconnect_pending']);
        }

        try {
            if (! $this->adapters->byKey($session->networkDevice->adapter)->disconnect($session)) {
                throw new RuntimeException('The network adapter did not confirm the disconnect.');
            }

            $session->update([
                'status' => 'stopped',
                'stopped_at' => now(),
                'terminate_cause' => 'Admin-Reset',
            ]);
            $this->devices->markEvidence(
                $session->networkDevice,
                'coa',
                'Disconnect-ACK received from the network device.',
                ['session' => $session->uuid],
            );
            HotspotSessionUpdated::dispatch($session->refresh());

            return $session;
        } catch (Throwable $exception) {
            $session->refresh();
            if ($session->status === 'disconnect_pending') {
                $session->update(['status' => 'active']);
            }

            throw $exception;
        }
    }
}
