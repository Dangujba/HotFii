<?php

namespace App\Console\Commands;

use App\Models\FeeLedgerEntry;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\Billing\CommerceFeeCalculator;
use App\Services\Vouchers\VoucherSaleTransactionRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillVoucherTransactions extends Command
{
    protected $signature = 'hotfii:backfill-voucher-transactions
        {--commit : Create the missing transaction rows. Without this the command only reports.}';

    protected $description = 'Create cash transaction rows for paid voucher redemptions that predate automatic transaction recording.';

    public function handle(VoucherSaleTransactionRecorder $recorder, CommerceFeeCalculator $fees): int
    {
        $vouchers = Voucher::query()
            ->whereNotNull('activated_at')
            ->where('is_complimentary', false)
            ->where('price_snapshot_kobo', '>', 0)
            ->with('organization', 'batch.accessPlan')
            ->orderBy('id')
            ->get();

        $existingReferences = Transaction::whereIn(
            'reference',
            $vouchers->map(fn (Voucher $voucher) => $recorder->reference($voucher)),
        )->pluck('reference')->flip();

        $missing = $vouchers
            ->reject(fn (Voucher $voucher) => $existingReferences->has($recorder->reference($voucher)))
            ->values();

        if ($missing->isEmpty()) {
            $this->info('No voucher transactions are missing.');

            return self::SUCCESS;
        }

        $this->table(
            ['Organization', 'Missing transactions', 'Gross sales'],
            $missing->groupBy('organization_id')->map(function ($organizationVouchers) {
                return [
                    $organizationVouchers->first()->organization->name,
                    $organizationVouchers->count(),
                    '₦'.number_format($organizationVouchers->sum('price_snapshot_kobo') / 100, 2),
                ];
            })->values()->all(),
        );

        if (! $this->option('commit')) {
            $this->warn('Dry run. Re-run with --commit to create these transaction rows.');

            return self::SUCCESS;
        }

        $ledgerByVoucher = FeeLedgerEntry::query()
            ->where('source_type', 'voucher')
            ->whereIn('source_id', $missing->pluck('id'))
            ->get()
            ->keyBy('source_id');

        DB::transaction(function () use ($missing, $ledgerByVoucher, $recorder, $fees) {
            foreach ($missing as $voucher) {
                $feeAmount = $ledgerByVoucher->get($voucher->id)?->fee_amount_kobo
                    ?? $fees->quote($voucher->organization, $voucher->price_snapshot_kobo)
                        ->chargeablePercentageFeeKobo();

                $recorder->record($voucher, $feeAmount);
            }
        });

        $this->info(sprintf('%d voucher transaction(s) created.', $missing->count()));

        return self::SUCCESS;
    }
}
