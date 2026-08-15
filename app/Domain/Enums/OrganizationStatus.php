<?php

namespace App\Domain\Enums;

enum OrganizationStatus: string
{
    case Sandbox = 'sandbox';
    case Trial = 'trial';
    case PaymentReview = 'payment_review';
    case Live = 'live';
    case PaymentRejected = 'payment_rejected';
    case Grace = 'grace';
    case Suspended = 'suspended';
}