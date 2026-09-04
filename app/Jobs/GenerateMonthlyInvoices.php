<?php

namespace App\Jobs;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateMonthlyInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly ?string $period = null)
    {
        $this->onQueue('payments');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('monthly-invoices-'.$this->billingPeriod()))->expireAfter(180)];
    }

    public function handle(): void
    {
        $period = $this->billingPeriod();

        // Organizations are Live from registration, so status can no longer
        // stand in for "has started paying". An untouched account has no
        // trial_started_at and must never be invoiced for a subscription.
        Organization::query()
            ->whereNotNull('trial_started_at')
            ->where('status', '!=', OrganizationStatus::PaymentRejected)
            ->chunkById(100, function ($organizations) use ($period) {
                foreach ($organizations as $organization) {
                    $ledger = FeeLedgerEntry::where('organization_id', $organization->id)
                        ->whereDate('billing_period', $period)
                        ->get();

                    $sales = $ledger->sum('billable_sales_kobo');
                    $percentageFees = $ledger->sum('fee_amount_kobo');
                    $collected = $ledger->where('status', 'collected')->sum('fee_amount_kobo');

                    $sellerMinimum = $organization->mode === OrganizationMode::Commerce
                        && $organization->billing_plan === BillingPlan::StandardSeller
                            ? (int) config('hotfii.commerce.standard_minimum_kobo')
                            : 0;

                    $subscriptionBase = (int) (
                        config('hotfii.internal_plans.'.$organization->billing_plan->value.'.price_kobo')
                        ?? 0
                    );

                    $sellerFee = max($percentageFees, $sellerMinimum);
                    $total = $subscriptionBase + $sellerFee;
                    $balance = max(0, $total - $collected);

                    if ($balance > 0) {
                        Invoice::firstOrCreate(
                            ['organization_id' => $organization->id, 'billing_period' => $period],
                            [
                                'number' => 'HF-INV-'.Str::upper(Str::random(10)),
                                'subtotal_kobo' => $balance,
                                'total_kobo' => $balance,
                                'status' => 'open',
                                'due_at' => now()->addDays(7),
                            ],
                        );
                    }

                    if ($organization->billing_plan === BillingPlan::MicroSeller
                        && $sales > (int) config('hotfii.commerce.micro_sales_limit_kobo')) {
                        $organization->update(['billing_plan' => BillingPlan::StandardSeller]);
                    }
                }
            });
    }

    private function billingPeriod(): string
    {
        return ($this->period ? Carbon::parse($this->period) : now()->subMonth())
            ->startOfMonth()
            ->toDateString();
    }
}