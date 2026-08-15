<?php

namespace App\Jobs;

use App\Events\HotspotSessionUpdated;
use App\Models\HotspotSession;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Network\RouterAdapterRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class DisconnectHotspotSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(public readonly HotspotSession $session)
    {
        $this->onQueue('critical');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('session-disconnect-'.$this->session->id))->expireAfter(30)];
    }

    public function backoff(): array { return [5, 20, 60]; }

    public function handle(RouterAdapterRegistry $adapters, NetworkDeviceManager $devices): void
    {
        $session = $this->session->refresh();
        if (! in_array($session->status, ['active', 'disconnect_pending'], true)) {
            return;
        }

        if (! $adapters->byKey($session->networkDevice->adapter)->disconnect($session)) {
            throw new RuntimeException('The network adapter did not confirm the disconnect.');
        }

        $session->update([
            'status' => 'stopped',
            'stopped_at' => now(),
            'terminate_cause' => 'Admin-Reset',
        ]);
        $devices->markEvidence(
            $session->networkDevice,
            'coa',
            'Disconnect-ACK received from the network device.',
            ['session' => $session->uuid],
        );
        HotspotSessionUpdated::dispatch($session->refresh());
    }

    public function failed(): void
    {
        $this->session->refresh()->update(['status' => 'active']);
    }
}