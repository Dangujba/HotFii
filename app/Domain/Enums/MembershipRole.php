<?php

namespace App\Domain\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Technician = 'technician';
    case Accountant = 'accountant';
    case Agent = 'agent';
    case Viewer = 'viewer';

    public function canManageNetwork(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Technician], true);
    }
}