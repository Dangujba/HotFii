<?php

namespace App\Domain\Enums;

enum VoucherStatus: string
{
    case Generated = 'generated';
    case Printed = 'printed';
    case Assigned = 'assigned';
    case Sold = 'sold';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}