<?php

namespace App\Events;

use App\Models\HotspotSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HotspotSessionUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly HotspotSession $session) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->session->organization->uuid)];
    }

    public function broadcastAs(): string { return 'session.updated'; }

    public function broadcastWith(): array
    {
        return [
            'session' => $this->session->uuid,
            'status' => $this->session->status,
            'input_bytes' => $this->session->input_bytes,
            'output_bytes' => $this->session->output_bytes,
        ];
    }
}