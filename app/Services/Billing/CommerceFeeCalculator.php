<?php

namespace App\Services\Billing;

use App\Data\FeeQuote;
use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Models\Organization;

class CommerceFeeCalculator
{
    public function quote(Organization $organization, int $billableSalesKobo): FeeQuote
    {
        $billableSalesKobo = max(0, $billableSalesKobo);
        $percentage = intdiv($billableSalesKobo * (int) config('hotfii.commerce.platform_fee_bps'), 10_000);

        // Nothing is charged before the trial clock has started, which happens
        // on the first real activation rather than at registration.
        $beforeFirstActivation = ! $organization->trial_started_at;

        if ($organization->inTrial() || $organization->billing_plan === BillingPlan::Sandbox || $beforeFirstActivation) {
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