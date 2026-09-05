<?php

namespace App\Domain\Enums;

enum RouterVendor: string
{
    case Generic = 'generic';
    case Mikrotik = 'mikrotik';
    case Unifi = 'unifi';
    case Omada = 'omada';
    case Openwrt = 'openwrt';
    case Ruijie = 'ruijie';
    case Cambium = 'cambium';
    case Cisco = 'cisco';
    case Huawei = 'huawei';
    case Dlink = 'dlink';

    public function label(): string
    {
        return match ($this) {
            self::Mikrotik => 'MikroTik', self::Unifi => 'Ubiquiti UniFi', self::Omada => 'TP-Link Omada', self::Openwrt => 'OpenWrt',
            self::Ruijie => 'Ruijie / Reyee', self::Dlink => 'D-Link', default => ucfirst($this->value),
        };
    }
}