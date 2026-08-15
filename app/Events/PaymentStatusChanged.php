<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Transaction $transaction) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('organizations.'.$this->transaction->organization->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'payment.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction' => $this->transaction->uuid,
            'status' => $this->transaction->status->value,
            'amount_kobo' => $this->transaction->gross_amount_kobo,
        ];
    }
}