<?php

namespace App\Console\Commands;

use App\Domain\Enums\OrganizationMode;
use App\Models\FeeLedgerEntry;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\Billing\CommerceFeeCalculator;
use App\Services\Billing\TrialManager;
use App\Services\Vouchers\VoucherSaleTransactionRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateCommerceFees extends Command
{
    protected $signature = 'hotfii:recalculate-commerce-fees
        {--commit : Write the corrections. Without this the command only reports.}';

    protected $description = 'Apply the transaction percentage to historical seller fees that were waived by Trial or Sandbox.';

    public function handle(
        CommerceFeeCalculator $fees,
        TrialManager $trials,
        VoucherSaleTransactionRecorder $voucherSales,
    ): int {
        $entries = FeeLedgerEntry::query()
            ->whereIn('source_type', ['transaction', 'voucher'])
            ->where('billable_sales_kobo', '>', 0)
            ->where('fee_amount_kobo', 0)
            ->with('organization')
            ->orderBy('id')
            ->get()
            ->filter(function (FeeLedgerEntry $entry) use ($fees): bool {
                if (! $entry->organization || $entry->organization->mode === OrganizationMode::Internal) {
                    return false;
                }

                return $entry->fee_amount_kobo !== $fees
                    ->quote($entry->organization, $entry->billable_sales_kobo)
                    ->chargeablePercentageFeeKobo();
            })
            ->values();

        $organizations = Organization::query()
            ->where('mode', OrganizationMode::Commerce->value)
            ->whereNull('trial_started_at')
            ->whereHas('transactions', fn ($query) => $query->where('status', 'successful'))
            ->with(['transactions' => fn ($query) => $query
                ->where('status', 'successful')
                ->orderByRaw('COALESCE(paid_at, created_at)')])
            ->get();

        if ($entries->isEmpty() && $organizations->isEmpty()) {
            $this->info('No commerce fees or billing start dates need correction.');

            return self::SUCCESS;
        }

        if ($entries->isNotEmpty()) {
            $this->table(
                ['Organization', 'Source', 'Sale', 'Was', 'Becomes'],
                $entries->map(function (FeeLedgerEntry $entry) use ($fees): array {
                    $expected = $fees->quote($entry->organization, $entry->billable_sales_kobo)
                        ->chargeablePercentageFeeKobo();

                    return [
                        $entry->organization->name,
                        $entry->source_type.' #'.$entry->source_id,
                        '₦'.number_format($entry->billable_sales_kobo / 100, 2),
                        '₦'.number_format($entry->fee_amount_kobo / 100, 2),
                        '₦'.number_format($expected / 100, 2),
                    ];
                })->all(),
            );
        }

        if ($organizations->isNotEmpty()) {
            $this->table(
                ['Organization', 'Billing starts'],
                $organizations->map(function (Organization $organization): array {
                    $first = $organization->transactions->first();

                    return [$organization->name, ($first->paid_at ?? $first->created_at)->toDateTimeString()];
                })->all(),
            );
        }

        if (! $this->option('commit')) {
            $this->warn('Dry run. Re-run with --commit to write these corrections.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($entries, $organizations, $fees, $trials, $voucherSales): void {
            foreach ($organizations as $organization) {
                $first = $organization->transactions->first();
                $startedAt = $first->paid_at ?? $first->created_at;
                $trialEndsAt = $startedAt->copy()->addDays((int) config('hotfii.commerce.trial_days'));
                $trialSales = (int) $organization->transactions
                    ->filter(fn (Transaction $transaction) => ($transaction->paid_at ?? $transaction->created_at)->lte($trialEndsAt))
                    ->sum('billable_sales_kobo');

                $organization = $trials->start($organization, $startedAt);
                $organization->forceFill(['trial_sales_kobo' => $trialSales])->save();
            }

            foreach ($entries as $entry) {
                $expected = $fees->quote($entry->organization, $entry->billable_sales_kobo)
                    ->chargeablePercentageFeeKobo();
                $wasActuallyCollected = $entry->status === 'collected' && $entry->fee_amount_kobo > 0;

                $entry->update([
                    'fee_amount_kobo' => $expected,
                    'status' => $wasActuallyCollected ? 'collected' : 'accrued',
                    'metadata' => array_merge($entry->metadata ?? [], [
                        'fee_recalculated_at' => now()->toIso8601String(),
                        'reason' => 'Trial and Sandbox sales are billable from the first paid activation.',
                    ]),
                ]);

                if ($entry->source_type === 'transaction') {
                    Transaction::whereKey($entry->source_id)->update(['platform_fee_kobo' => $expected]);
                    continue;
                }

                $voucher = Voucher::find($entry->source_id);
                if ($voucher) {
                    Transaction::where('reference', $voucherSales->reference($voucher))
                        ->update(['platform_fee_kobo' => $expected]);
                }
            }
        });

        $this->info(sprintf(
            '%d fee entr%s corrected; %d billing start date%s restored.',
            $entries->count(),
            $entries->count() === 1 ? 'y' : 'ies',
            $organizations->count(),
            $organizations->count() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
