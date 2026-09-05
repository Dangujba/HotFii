<?php

namespace App\Domain\Enums;

enum RouterVendor: string
{
    case Generic = 'generic';
    case Mikrotik = 'mikrotik';
    case Unifi = 'unifi';
    case Omada = 'omada';
    case Ruijie = 'ruijie';
    case Cambium = 'cambium';
    case Cisco = 'cisco';
    case Huawei = 'huawei';
    case Dlink = 'dlink';

    public function label(): string
    {
        return match ($this) {
            self::Mikrotik => 'MikroTik', self::Unifi => 'Ubiquiti UniFi', self::Omada => 'TP-Link Omada',
            self::Ruijie => 'Ruijie / Reyee', self::Dlink => 'D-Link', self::Generic => 'Generic / OpenWrt',
            default => ucfirst($this->value),
        };
    }

    /**
     * The vendors an operator may pick when adding a device.
     *
     * Every case stays in the enum so existing devices keep resolving and the
     * adapters remain available, but only vendors proven against real hardware
     * are offered. Driven by config so a vendor can be released without a
     * deploy of this file. See config/hotfii.php.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        $allowed = config('hotfii.selectable_vendors', []);

        return array_values(array_filter(
            self::cases(),
            fn (self $vendor) => in_array($vendor->value, $allowed, true),
        ));
    }
}