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
    public function test_sandbox_and_trial_fees_are_waived(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Sandbox,
        ]);

        $quote = app(CommerceFeeCalculator::class)->quote($organization, 10000000);

        $this->assertSame(200000, $quote->percentageFeeKobo);
        $this->assertSame(0, $quote->chargeablePercentageFeeKobo());
        $this->assertSame(0, $quote->totalFeeKobo);
    }

    public function test_a_live_organization_that_never_sold_is_not_charged(): void
    {
        // Live is now the registration default, so status alone must not make
        // an untouched organization billable.
        $organization = new Organization([
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::StandardSeller,
        ]);

        $quote = app(CommerceFeeCalculator::class)->quote($organization, 10000000);

        $this->assertSame(0, $quote->totalFeeKobo);
        $this->assertSame('trial_or_sandbox', $quote->reason);
    }

    public function test_standard_uses_two_percent_or_monthly_minimum(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::StandardSeller,
        ]);
        $organization->trial_started_at = now()->subMonth();

        $low = app(CommerceFeeCalculator::class)->quote($organization, 10000000);
        $high = app(CommerceFeeCalculator::class)->quote($organization, 50000000);

        $this->assertSame(250000, $low->totalFeeKobo);
        $this->assertSame(1000000, $high->totalFeeKobo);
    }

    public function test_hybrid_never_adds_seller_minimum(): void
    {
        $organization = new Organization([
            'mode' => OrganizationMode::Hybrid,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::Organization20,
        ]);
        $organization->trial_started_at = now()->subMonth();

        $quote = app(CommerceFeeCalculator::class)->quote($organization, 1000000);

        $this->assertSame(20000, $quote->totalFeeKobo);
        $this->assertSame(0, $quote->minimumFeeKobo);
    }
}