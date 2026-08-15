<?php

namespace App\Domain\Enums;

enum OrganizationMode: string
{
    case Commerce = 'commerce';
    case Internal = 'internal';
    case Hybrid = 'hybrid';

    public function sellsAccess(): bool
    {
        return $this !== self::Internal;
    }
}