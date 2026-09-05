<?php

namespace App\Console\Commands;

use App\Models\FeeLedgerEntry;
use App\Models\Voucher;
use App\Services\Billing\CommerceFeeCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs vouchers sold at a price that was never plausible.
 *
 * Batch VB-260905-9A7KKQ was generated at 2 kobo against a ₦500 plan, so ten
 * vouchers that took ₦5,000 of real cash were booked as ₦0.20. The operator's
 * sales tiles read ₦0 and HotFii's own commission was calculated on 20 kobo.
 * The generation path is fixed, but the rows it already wrote are still wrong,
 * and they are wrong in the direction of under-billing a real business.
 *
 * Corrects price_snapshot_kobo to the plan price, moves the organization sales
 * counters by the difference, and re-quotes the fee ledger entry. Dry run by
 * default: nothing moves until --commit is passed.
 */
class RepriceUnderpricedVouchers extends Command
{
    protected $signature = 'hotfii:reprice-vouchers
        {--commit : Write the corrections. Without this the command only reports.}
        {--threshold=100 : Treat a paid snapshot below this many kobo as a pricing fault.}';

    protected $description = 'Reprice activated vouchers whose snapshot price is implausibly low, and correct the sales counters and fee ledger to match.';

    public function handle(CommerceFeeCalculator $fees): int
    {
        $threshold = (int) $this->option('threshold');
        $commit = (bool) $this->option('commit');

        $vouchers = Voucher::query()
            ->whereNotNull('activated_at')
            ->where('is_complimentary', false)
            ->where('price_snapshot_kobo', '<', $threshold)
            ->with('batch.accessPlan', 'organization')
            ->get()
            // A plan legitimately priced at 0 is free or internal access, not a
            // fault to repair. Only paid plans are in scope.
            ->filter(fn (Voucher $voucher) => $voucher->batch?->accessPlan?->access_type === 'paid'
                && ($voucher->batch->accessPlan->price_kobo ?? 0) >= $threshold);

        if ($vouchers->isEmpty()) {
            $this->info('No underpriced activated vouchers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Voucher', 'Batch', 'Plan', 'Was', 'Becomes'],
            $vouchers->map(fn (Voucher $voucher) => [
                '••••'.$voucher->code_last_four,
                $voucher->batch->reference,
                $voucher->batch->accessPlan->name,
                $voucher->price_snapshot_kobo,
                $voucher->batch->accessPlan->price_kobo,
            ])->all(),
        );

        $shortfall = $vouchers->sum(
            fn (Voucher $voucher) => $voucher->batch->accessPlan->price_kobo - $voucher->price_snapshot_kobo
        );

        $this->line(sprintf(
            '%d voucher(s), understated by ₦%s in total.',
            $vouchers->count(),
            number_format($shortfall / 100, 2),
        ));

        if (! $commit) {
            $this->warn('Dry run. Re-run with --commit to write these corrections.');

            return self::SUCCESS;
        }

        // One transaction: a half-applied repair would leave the counters
        // disagreeing with the vouchers, which is worse than the original fault
        // because it looks correct.
        DB::transaction(function () use ($vouchers, $fees) {
            foreach ($vouchers as $voucher) {
                $plan = $voucher->batch->accessPlan;
                $was = $voucher->price_snapshot_kobo;
                $difference = $plan->price_kobo - $was;

                $voucher->update(['price_snapshot_kobo' => $plan->price_kobo]);

                $organization = $voucher->organization;

                // The original activation already moved these by the wrong
                // amount, so only the difference is applied here.
                $organization->increment('monthly_sales_kobo', $difference);
                if ($organization->inTrial()) {
                    $organization->increment('trial_sales_kobo', $difference);
                }

                $quote = $fees->quote($organization, $plan->price_kobo);

                FeeLedgerEntry::updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'source_type' => 'voucher',
                        'source_id' => $voucher->id,
                    ],
                    [
                        'billing_period' => $voucher->activated_at->startOfMonth()->toDateString(),
                        'billable_sales_kobo' => $plan->price_kobo,
                        'fee_amount_kobo' => $quote->chargeablePercentageFeeKobo(),
                        'status' => 'accrued',
                        'metadata' => [
                            'repriced_from_kobo' => $was,
                            'repriced_at' => now()->toIso8601String(),
                            'reason' => 'Batch generated below plan price before the pricing floor existed.',
                        ],
                    ],
                );

                $this->line(sprintf(
                    'Repriced ••••%s from %d to %d kobo.',
                    $voucher->code_last_four,
                    $was,
                    $plan->price_kobo,
                ));
            }
        });

        $this->info('Corrections written.');

        return self::SUCCESS;
    }
}
