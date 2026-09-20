<?php

namespace App\Jobs;

use App\Models\HotspotSession;
use App\Services\Network\SessionDisconnectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

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

    public function backoff(): array
    {
        return [5, 20, 60];
    }

    public function handle(SessionDisconnectService $disconnects): void
    {
        $session = $this->session->refresh();
        if (! in_array($session->status, ['active', 'disconnect_pending'], true)) {
            return;
        }

        $disconnects->disconnect($session);
    }

    public function failed(): void
    {
        $session = $this->session->refresh();
        if ($session->status === 'disconnect_pending') {
            $session->update(['status' => 'active']);
        }
    }
}
