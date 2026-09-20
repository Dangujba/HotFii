<?php

namespace App\Services\Billing;

use App\Domain\Enums\OrganizationMode;
use App\Models\FeeLedgerEntry;
use App\Models\NetworkDevice;
use App\Models\Organization;

final class OrganizationFinanceService
{
    public function __construct(private readonly CommerceMonthlyFeeCalculator $monthlyFees) {}

    public function current(Organization $organization, ?NetworkDevice $router = null): array
    {
        $period = now()->startOfMonth()->toDateString();
        $allEntries = FeeLedgerEntry::where('organization_id', $organization->id)
            ->whereDate('billing_period', $period)
            ->get();
        $entries = $router
            ? $allEntries->where('network_device_id', $router->id)
            : $allEntries;
        $allSales = (int) $allEntries->sum('billable_sales_kobo');
        $allCollected = (int) $allEntries->where('status', 'collected')->sum('fee_amount_kobo');
        $billingStarted = $organization->trial_started_at !== null || $allSales > 0;
        $subscriptionBase = $billingStarted
            ? (int) (config('hotfii.internal_plans.'.$organization->billing_plan->value.'.price_kobo') ?? 0)
            : 0;
        $sellerFee = match ($organization->mode) {
            OrganizationMode::Commerce => $billingStarted ? $this->monthlyFees->calculate($allSales) : 0,
            OrganizationMode::Hybrid => (int) $allEntries->sum('fee_amount_kobo'),
            default => 0,
        };
        $estimatedMonthEndFee = $subscriptionBase + $sellerFee;

        return [
            'sales' => (int) $entries->sum('billable_sales_kobo'),
            'fees' => (int) $entries->sum('fee_amount_kobo'),
            'accrued' => (int) $entries->where('status', 'accrued')->sum('fee_amount_kobo'),
            'collected' => (int) $entries->where('status', 'collected')->sum('fee_amount_kobo'),
            'estimated_month_end_fee' => $estimatedMonthEndFee,
            'estimated_invoice_balance' => max(0, $estimatedMonthEndFee - $allCollected),
        ];
    }
}
