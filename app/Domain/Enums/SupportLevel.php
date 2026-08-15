<?php

namespace App\Domain\Enums;

enum SupportLevel: string
{
    case Compatible = 'compatible';
    case Beta = 'beta';
    case Certified = 'certified';
}