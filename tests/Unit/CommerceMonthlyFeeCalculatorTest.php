<?php

namespace Tests\Unit;

use App\Services\Billing\CommerceMonthlyFeeCalculator;
use Tests\TestCase;

class CommerceMonthlyFeeCalculatorTest extends TestCase
{
    public function test_the_minimum_covers_sales_up_to_fifty_thousand_naira(): void
    {
        $calculator = app(CommerceMonthlyFeeCalculator::class);

        $this->assertSame(250000, $calculator->calculate(0));
        $this->assertSame(250000, $calculator->calculate(50_000_00));
    }

    public function test_two_percent_is_added_only_to_sales_above_fifty_thousand_naira(): void
    {
        $calculator = app(CommerceMonthlyFeeCalculator::class);

        $this->assertSame(270000, $calculator->calculate(60_000_00));
        $this->assertSame(350000, $calculator->calculate(100_000_00));
        $this->assertSame(550000, $calculator->calculate(200_000_00));
    }
}
