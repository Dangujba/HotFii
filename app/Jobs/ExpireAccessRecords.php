<?php

namespace App\Jobs;

use App\Domain\Enums\VoucherStatus;
use App\Models\AccessCredential;
use App\Models\HotspotSession;
use App\Models\Voucher;
use App\Services\Radius\RadiusCredentialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireAccessRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('critical');
    }

    public function handle(RadiusCredentialService $credentials): void
    {
        Voucher::query()
            ->where('status', VoucherStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => VoucherStatus::Expired]);

        AccessCredential::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, fn ($records) => $records->each(fn ($credential) => $credentials->revoke($credential)));

        HotspotSession::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'stopped_at' => now(),
                'terminate_cause' => 'Session-Timeout',
            ]);
    }
}