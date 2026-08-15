<?php

namespace App\Domain\Enums;

enum NetworkDeviceStatus: string
{
    case Pending = 'pending';
    case Testing = 'testing';
    case Online = 'online';
    case Offline = 'offline';
    case Failed = 'failed';
}