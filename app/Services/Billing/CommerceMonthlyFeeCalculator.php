<?php

namespace App\Services\Billing;

final class CommerceMonthlyFeeCalculator
{
    public function calculate(int $salesKobo): int
    {
        $salesKobo = max(0, $salesKobo);
        $includedSalesKobo = (int) config('hotfii.commerce.minimum_included_sales_kobo');
        $minimumFeeKobo = (int) config('hotfii.commerce.standard_minimum_kobo');

        if ($salesKobo <= $includedSalesKobo) {
            return $minimumFeeKobo;
        }

        $excessSalesKobo = $salesKobo - $includedSalesKobo;
        $excessFeeKobo = intdiv(
            $excessSalesKobo * (int) config('hotfii.commerce.platform_fee_bps'),
            10_000,
        );

        return $minimumFeeKobo + $excessFeeKobo;
    }
}
