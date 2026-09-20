<?php

namespace Tests\Feature\Operator;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Http\Controllers\Operator\ReportsController;
use App\Models\AccessPlan;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsChartDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_separate_real_online_voucher_and_direct_cash_sales(): void
    {
        $organization = Organization::create([
            'name' => 'Chart Network',
            'slug' => 'chart-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Trial,
            'billing_plan' => BillingPlan::Sandbox,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $plan = AccessPlan::create([
            'organization_id' => $organization->id,
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
        ]);

        $this->sale($organization, $plan, 'HF-ONLINE-1', 'online', 1_000_00);
        $this->sale($organization, $plan, 'HF-VCH-ONE', 'cash', 500_00);
        $this->sale($organization, $plan, 'HF-CASH-ONE', 'cash', 700_00);

        $today = now()->toDateString();
        $view = app(ReportsController::class)->index(
            Request::create('/reports', 'GET', ['from' => $today, 'to' => $today]),
            $organization,
        );
        $data = $view->getData();
        $channels = $data['channels']->keyBy('key');

        $this->assertSame(3, (int) $data['summary']->sales);
        $this->assertSame(220_000, (int) $data['summary']->gross_kobo);
        $this->assertSame(1, $channels['online']['sales']);
        $this->assertSame(1000.0, $channels['online']['value']);
        $this->assertSame(1, $channels['voucher']['sales']);
        $this->assertSame(500.0, $channels['voucher']['value']);
        $this->assertSame(1, $channels['cash']['sales']);
        $this->assertSame(700.0, $channels['cash']['value']);
        $this->assertSame([1000.0], $data['salesTrend']['series']['online']);
        $this->assertSame([500.0], $data['salesTrend']['series']['voucher']);
        $this->assertSame([700.0], $data['salesTrend']['series']['cash']);
        $this->assertSame(220_000, (int) $data['topPlans']->sole()->total);
    }

    private function sale(
        Organization $organization,
        AccessPlan $plan,
        string $reference,
        string $channel,
        int $amountKobo,
    ): void {
        Transaction::create([
            'organization_id' => $organization->id,
            'access_plan_id' => $plan->id,
            'reference' => $reference,
            'provider' => $channel === 'online' ? 'paystack' : 'manual',
            'channel' => $channel,
            'status' => PaymentStatus::Successful,
            'gross_amount_kobo' => $amountKobo,
            'platform_fee_kobo' => intdiv($amountKobo * 200, 10_000),
            'billable_sales_kobo' => $amountKobo,
            'paid_at' => now(),
        ]);
    }
}
