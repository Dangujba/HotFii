<?php

namespace App\Jobs;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Events\NetworkDeviceStatusChanged;
use App\Models\NetworkDevice;
use App\Notifications\HotFiiAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOfflineNetworkDevices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue('network');
    }

    public function handle(): void
    {
        NetworkDevice::query()
            ->where('status', NetworkDeviceStatus::Online)
            ->where(fn ($query) => $query
                ->whereNull('last_heartbeat_at')
                ->orWhere('last_heartbeat_at', '<', now()->subSeconds(90)))
            ->chunkById(100, function ($devices) {
                foreach ($devices as $device) {
                    $device->update(['status' => NetworkDeviceStatus::Offline]);
                    NetworkDeviceStatusChanged::dispatch($device->refresh());
                    $recipients = $device->organization->users()->wherePivotIn('role', ['owner', 'manager', 'technician'])->get();
                    Notification::send($recipients, new HotFiiAlert(
                        'Router offline: '.$device->name,
                        'No heartbeat has been received for more than 90 seconds.',
                        route('network.devices.show', $device),
                    ));
                }
            });
    }
}