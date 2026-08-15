<?php

namespace App\Contracts\Network;

use App\Models\AccessPlan;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use Throwable;

interface RouterAdapter
{
    public function key(): string;
    public function discover(array $context = []): array;
    public function capabilities(): array;
    public function provisioning(NetworkDevice $device): array;
    public function provision(NetworkDevice $device): array;
    public function testManagementConnection(NetworkDevice $device): array;
    public function testRadiusAuthentication(NetworkDevice $device): array;
    public function testAccounting(NetworkDevice $device): array;
    public function testCaptivePortal(NetworkDevice $device): array;
    public function tests(NetworkDevice $device): array;
    public function disconnect(HotspotSession $session): bool;
    public function planAttributes(AccessPlan $plan): array;
    public function healthMetrics(NetworkDevice $device): array;
    public function synchronize(NetworkDevice $device): array;
    public function normalizeError(Throwable $exception): array;
}