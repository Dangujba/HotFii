<?php

namespace App\Jobs;

use App\Models\NetworkDevice;
use App\Services\Network\NetworkDeviceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunNetworkDeviceTests implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 45;

    public function __construct(public readonly NetworkDevice $device)
    {
        $this->onQueue('network');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('device-tests-'.$this->device->id))->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [5, 20, 60];
    }

    public function tags(): array
    {
        return ['organization:'.$this->device->organization_id, 'network-device:'.$this->device->uuid];
    }

    public function handle(NetworkDeviceManager $manager): void
    {
        $manager->runTests($this->device->refresh());
    }
}