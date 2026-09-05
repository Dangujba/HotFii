<?php

namespace Tests\Unit;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Services\Billing\CommerceFeeCalculator;
use Tests\TestCase;

class CommerceFeeCalculatorTest extends TestCase
{
    public function test_trial_and_sandbox_sales_are_charged_the_transaction_percentage(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Trial,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
        $organization->trial_started_at = now()->subDay();
        $organization->trial_ends_at = now()->addDays(13);

        $quote = app(CommerceFeeCalculator::class)->quote($organization, 10000000);

        $this->assertSame(200000, $quote->percentageFeeKobo);
        $this->assertSame(200000, $quote->chargeablePercentageFeeKobo());
        $this->assertSame(200000, $quote->totalFeeKobo);
    }

    public function test_the_first_live_sale_is_quoted_at_the_transaction_percentage(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Sandbox,
        ]);

        $quote = app(CommerceFeeCalculator::class)->quote($organization, 10000000);

        $this->assertSame(200000, $quote->chargeablePercentageFeeKobo());
        $this->assertSame('percentage_only', $quote->reason);
    }

    public function test_every_standard_sale_is_quoted_at_two_percent(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::StandardSeller,
        ]);
        $low = app(CommerceFeeCalculator::class)->quote($organization, 10_000_00);
        $high = app(CommerceFeeCalculator::class)->quote($organization, 500_000_00);

        $this->assertSame(20_000, $low->totalFeeKobo);
        $this->assertSame(1_000_000, $high->totalFeeKobo);
        $this->assertSame(0, $low->minimumFeeKobo);
    }

    public function test_hybrid_never_adds_seller_minimum(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Hybrid,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Organization20,
        ]);
        $quote = app(CommerceFeeCalculator::class)->quote($organization, 1000000);

        $this->assertSame(20000, $quote->totalFeeKobo);
        $this->assertSame(0, $quote->minimumFeeKobo);
    }
}
