<?php

namespace App\Jobs;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\Billing\CommerceMonthlyFeeCalculator;
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

    public function handle(?CommerceMonthlyFeeCalculator $monthlyFees = null): void
    {
        $monthlyFees ??= app(CommerceMonthlyFeeCalculator::class);
        $period = $this->billingPeriod();

        // Registration alone does not start billing. The first paid activation
        // sets trial_started_at, after which Trial and Sandbox are lifecycle
        // labels only and never exempt real sales from fees or invoicing.
        Organization::query()
            ->whereNotNull('trial_started_at')
            ->chunkById(100, function ($organizations) use ($period, $monthlyFees) {
                foreach ($organizations as $organization) {
                    $ledger = FeeLedgerEntry::where('organization_id', $organization->id)
                        ->whereDate('billing_period', $period)
                        ->get();

                    $sales = $ledger->sum('billable_sales_kobo');
                    $percentageFees = $ledger->sum('fee_amount_kobo');
                    $collected = $ledger->where('status', 'collected')->sum('fee_amount_kobo');

                    $subscriptionBase = (int) (
                        config('hotfii.internal_plans.'.$organization->billing_plan->value.'.price_kobo')
                        ?? 0
                    );

                    $sellerFee = match ($organization->mode) {
                        OrganizationMode::Commerce => $monthlyFees->calculate($sales),
                        OrganizationMode::Hybrid => $percentageFees,
                        default => 0,
                    };
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
