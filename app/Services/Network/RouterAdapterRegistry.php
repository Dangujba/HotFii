<?php

namespace App\Services\Network;

use App\Contracts\Network\RouterAdapter;
use App\Domain\Enums\RouterVendor;
use InvalidArgumentException;

class RouterAdapterRegistry
{
    public function __construct(
        private readonly GenericRadiusAdapter $generic,
        private readonly MikrotikRouterOsAdapter $mikrotik,
        private readonly UnifiAdapter $unifi,
        private readonly OmadaAdapter $omada,
    ) {}

    public function for(RouterVendor|string $vendor): RouterAdapter
    {
        $value = $vendor instanceof RouterVendor
            ? $vendor
            : RouterVendor::from($vendor);

        return match ($value) {
            RouterVendor::Mikrotik => $this->mikrotik,
            RouterVendor::Unifi => $this->unifi,
            RouterVendor::Omada => $this->omada,
            default => $this->generic,
        };
    }

    public function byKey(string $key): RouterAdapter
    {
        return match ($key) {
            'mikrotik-routeros' => $this->mikrotik,
            'unifi-network' => $this->unifi,
            'tp-link-omada' => $this->omada,
            'generic-radius' => $this->generic,
            default => throw new InvalidArgumentException(
                "Unknown router adapter: {$key}"
            ),
        };
    }
}
