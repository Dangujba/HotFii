<?php

namespace App\Domain\Enums;

enum BillingPlan: string
{
    case Sandbox = 'sandbox';
    case MicroSeller = 'micro_seller';
    case StandardSeller = 'standard_seller';
    case Organization20 = 'organization_20';
    case Organization50 = 'organization_50';
    case Organization250 = 'organization_250';
    case Institution1000 = 'institution_1000';
    case Enterprise = 'enterprise';
}