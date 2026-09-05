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

        if ($organization->mode === OrganizationMode::Internal) {
            return new FeeQuote($billableSalesKobo, 0, 0, 0, 'internal_subscription');
        }

        // Every real paid activation is quoted at the running percentage. The
        // monthly minimum and included-sales band are reconciled once, at the
        // end of the month, by CommerceMonthlyFeeCalculator.
        return new FeeQuote($billableSalesKobo, $percentage, 0, $percentage, 'percentage_only');
    }

    public function shouldGraduateFromMicro(Organization $organization, int $monthlySalesKobo): bool
    {
        return $organization->billing_plan === BillingPlan::MicroSeller
            && $monthlySalesKobo > (int) config('hotfii.commerce.micro_sales_limit_kobo');
    }
}
