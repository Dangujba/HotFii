<?php

namespace App\Services\Billing;

use App\Data\FeeQuote;
use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;

class CommerceFeeCalculator
{
    public function quote(Organization $organization, int $billableSalesKobo): FeeQuote
    {
        $billableSalesKobo = max(0, $billableSalesKobo);
        $percentage = intdiv($billableSalesKobo * (int) config('hotfii.commerce.platform_fee_bps'), 10_000);

        $firstLiveActivation = $organization->status === OrganizationStatus::Live
            && $organization->live_payments_enabled_at
            && ! $organization->trial_started_at;

        if ($organization->inTrial() || $organization->billing_plan === BillingPlan::Sandbox || $firstLiveActivation) {
            return new FeeQuote($billableSalesKobo, $percentage, 0, 0, 'trial_or_sandbox');
        }

        if ($organization->mode === OrganizationMode::Internal) {
            return new FeeQuote($billableSalesKobo, 0, 0, 0, 'internal_subscription');
        }

        if ($organization->mode === OrganizationMode::Hybrid || $organization->billing_plan === BillingPlan::MicroSeller) {
            return new FeeQuote($billableSalesKobo, $percentage, 0, $percentage, 'percentage_only');
        }

        $minimum = (int) config('hotfii.commerce.standard_minimum_kobo');

        return new FeeQuote($billableSalesKobo, $percentage, $minimum, max($percentage, $minimum), 'standard_minimum_or_percentage');
    }

    public function shouldGraduateFromMicro(Organization $organization, int $monthlySalesKobo): bool
    {
        return $organization->billing_plan === BillingPlan::MicroSeller
            && $monthlySalesKobo > (int) config('hotfii.commerce.micro_sales_limit_kobo');
    }
}