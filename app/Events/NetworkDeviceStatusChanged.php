<?php

namespace App\Events;

use App\Models\NetworkDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NetworkDeviceStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly NetworkDevice $device) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->device->organization->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'network-device.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'device' => $this->device->uuid,
            'status' => $this->device->status->value,
            'last_heartbeat_at' => $this->device->last_heartbeat_at?->toIso8601String(),
        ];
    }
}