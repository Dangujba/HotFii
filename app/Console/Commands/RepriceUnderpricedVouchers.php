<?php

namespace App\Console\Commands;

use App\Models\FeeLedgerEntry;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Billing\CommerceFeeCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs vouchers and batches sold at a price that was never plausible.
 *
 * Batch VB-260905-9A7KKQ was generated at 2 kobo against a ₦500 plan, so ten
 * vouchers that took ₦5,000 of real cash were booked as ₦0.20. The operator's
 * sales tiles read ₦0 and HotFii's own commission was calculated on 20 kobo.
 * The generation path is fixed, but the rows it already wrote are still wrong,
 * and they are wrong in the direction of under-billing a real business.
 *
 * Three things are corrected, because fixing only the sold vouchers leaves the
 * batch itself still cheap and the unsold half of it unusable:
 *
 *  - the batch's retail_price_kobo, which the printed value and every future
 *    voucher's snapshot are taken from;
 *  - price_snapshot_kobo on vouchers not yet activated, which the redeem guard
 *    would otherwise reject at the counter with "invalid price";
 *  - price_snapshot_kobo on activated vouchers, plus the organization sales
 *    counters and the fee ledger entry that were written from the wrong number.
 *
 * Dry run by default: nothing moves until --commit is passed.
 */
class RepriceUnderpricedVouchers extends Command
{
    protected $signature = 'hotfii:reprice-vouchers
        {--commit : Write the corrections. Without this the command only reports.}
        {--threshold=100 : Treat a paid price below this many kobo as a pricing fault.}';

    protected $description = 'Reprice voucher batches and vouchers whose price is implausibly low, and correct the sales counters and fee ledger to match.';

    public function handle(CommerceFeeCalculator $fees): int
    {
        $threshold = (int) $this->option('threshold');
        $commit = (bool) $this->option('commit');

        $batches = $this->underpricedBatches($threshold);
        $vouchers = $this->underpricedVouchers($threshold);

        if ($batches->isEmpty() && $vouchers->isEmpty()) {
            $this->info('Nothing underpriced found.');

            return self::SUCCESS;
        }

        $this->report($batches, $vouchers);

        if (! $commit) {
            $this->warn('Dry run. Re-run with --commit to write these corrections.');

            return self::SUCCESS;
        }

        // One transaction: a half-applied repair would leave the counters
        // disagreeing with the vouchers, which is worse than the original fault
        // because it looks correct.
        DB::transaction(function () use ($batches, $vouchers, $fees) {
            foreach ($batches as $batch) {
                $batch->update(['retail_price_kobo' => $batch->accessPlan->price_kobo]);
            }

            foreach ($vouchers as $voucher) {
                $this->repriceVoucher($voucher, $fees);
            }
        });

        $this->info('Corrections written.');

        return self::SUCCESS;
    }

    /**
     * Batches priced below the floor against a plan that is genuinely paid.
     *
     * @return \Illuminate\Support\Collection<int, VoucherBatch>
     */
    private function underpricedBatches(int $threshold)
    {
        return VoucherBatch::query()
            ->where('retail_price_kobo', '<', $threshold)
            ->with('accessPlan')
            ->get()
            ->filter(fn (VoucherBatch $batch) => $this->isPaidPlanAbove($batch->accessPlan, $threshold))
            ->values();
    }

    /**
     * Vouchers whose own snapshot is below the floor, sold or not.
     *
     * Complimentary vouchers are excluded: they are deliberately worth nothing.
     *
     * @return \Illuminate\Support\Collection<int, Voucher>
     */
    private function underpricedVouchers(int $threshold)
    {
        return Voucher::query()
            ->where('is_complimentary', false)
            ->where('price_snapshot_kobo', '<', $threshold)
            ->with('batch.accessPlan', 'organization')
            ->get()
            ->filter(fn (Voucher $voucher) => $this->isPaidPlanAbove($voucher->batch?->accessPlan, $threshold))
            ->values();
    }

    /**
     * A plan legitimately priced at 0 is free or internal access, not a fault to
     * repair, and a paid plan priced under the floor gives nothing to repair
     * towards.
     */
    private function isPaidPlanAbove(mixed $plan, int $threshold): bool
    {
        return $plan?->access_type === 'paid' && ($plan->price_kobo ?? 0) >= $threshold;
    }

    private function repriceVoucher(Voucher $voucher, CommerceFeeCalculator $fees): void
    {
        $plan = $voucher->batch->accessPlan;
        $was = $voucher->price_snapshot_kobo;

        $voucher->update(['price_snapshot_kobo' => $plan->price_kobo]);

        // An unsold voucher has moved no money yet, so there is no counter and
        // no fee entry to correct — activation will write them from the price
        // that is now right.
        if ($voucher->activated_at === null) {
            $this->line(sprintf(
                'Repriced unsold ••••%s from %d to %d kobo.',
                $voucher->code_last_four,
                $was,
                $plan->price_kobo,
            ));

            return;
        }

        $organization = $voucher->organization;
        $difference = $plan->price_kobo - $was;

        // The original activation already moved these by the wrong amount, so
        // only the difference is applied here.
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
            'Repriced activated ••••%s from %d to %d kobo, counters and fee moved by %+d.',
            $voucher->code_last_four,
            $was,
            $plan->price_kobo,
            $difference,
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VoucherBatch>  $batches
     * @param  \Illuminate\Support\Collection<int, Voucher>  $vouchers
     */
    private function report($batches, $vouchers): void
    {
        if ($batches->isNotEmpty()) {
            $this->table(
                ['Batch', 'Plan', 'Was', 'Becomes'],
                $batches->map(fn (VoucherBatch $batch) => [
                    $batch->reference,
                    $batch->accessPlan->name,
                    $batch->retail_price_kobo,
                    $batch->accessPlan->price_kobo,
                ])->all(),
            );
            $this->line(sprintf('%d batch price(s) to correct.', $batches->count()));
        }

        if ($vouchers->isEmpty()) {
            return;
        }

        $this->table(
            ['Voucher', 'Batch', 'Plan', 'State', 'Was', 'Becomes'],
            $vouchers->map(fn (Voucher $voucher) => [
                '••••'.$voucher->code_last_four,
                $voucher->batch->reference,
                $voucher->batch->accessPlan->name,
                $voucher->activated_at ? 'Activated' : 'Unsold',
                $voucher->price_snapshot_kobo,
                $voucher->batch->accessPlan->price_kobo,
            ])->all(),
        );

        // Only activated vouchers understate revenue. The unsold ones are a
        // future correctness problem, not a past accounting one, so they are
        // counted separately rather than inflating the shortfall.
        $activated = $vouchers->filter(fn (Voucher $voucher) => $voucher->activated_at !== null);
        $shortfall = $activated->sum(
            fn (Voucher $voucher) => $voucher->batch->accessPlan->price_kobo - $voucher->price_snapshot_kobo
        );

        $this->line(sprintf(
            '%d activated voucher(s), understated by ₦%s in total. %d unsold voucher(s) would be refused at activation until repriced.',
            $activated->count(),
            number_format($shortfall / 100, 2),
            $vouchers->count() - $activated->count(),
        ));
    }
}
