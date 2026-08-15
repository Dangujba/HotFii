<?php

namespace App\Events;

use App\Models\Voucher;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherActivated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Voucher $voucher) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->voucher->organization->uuid)];
    }

    public function broadcastAs(): string { return 'voucher.activated'; }

    public function broadcastWith(): array
    {
        return [
            'voucher' => $this->voucher->uuid,
            'status' => $this->voucher->status->value,
            'price_kobo' => $this->voucher->price_snapshot_kobo,
        ];
    }
}