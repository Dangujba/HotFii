<?php

namespace App\Services\Vouchers;

use App\Domain\Enums\PaymentStatus;
use App\Models\Transaction;
use App\Models\Voucher;
use LogicException;
use Illuminate\Support\Str;

final class VoucherSaleTransactionRecorder
{
    public function record(Voucher $voucher, int $platformFeeKobo): Transaction
    {
        if ($voucher->is_complimentary || $voucher->activated_at === null || $voucher->price_snapshot_kobo <= 0) {
            throw new LogicException('Only an activated paid voucher can be recorded as a sale.');
        }

        $reference = $this->reference($voucher);

        if ($transaction = Transaction::where('reference', $reference)->first()) {
            return $transaction;
        }

        $voucher->loadMissing('batch.accessPlan');

        $transaction = new Transaction([
            'organization_id' => $voucher->organization_id,
            'customer_id' => $voucher->customer_id,
            'access_plan_id' => $voucher->batch->access_plan_id,
            'reference' => $reference,
            'provider' => 'manual',
            'channel' => 'cash',
            'status' => PaymentStatus::Successful,
            'gross_amount_kobo' => $voucher->price_snapshot_kobo,
            'gateway_fee_kobo' => 0,
            'platform_fee_kobo' => $platformFeeKobo,
            'billable_sales_kobo' => $voucher->price_snapshot_kobo,
            'metadata' => [
                'source_type' => 'voucher_redemption',
                'voucher_id' => $voucher->id,
                'voucher_uuid' => $voucher->uuid,
                'voucher_batch_id' => $voucher->voucher_batch_id,
                'recorded_automatically' => true,
            ],
            'paid_at' => $voucher->activated_at,
        ]);

        // A backfilled sale belongs on the day the voucher was redeemed, not
        // the day the repair command happened to run.
        $transaction->created_at = $voucher->activated_at;
        $transaction->updated_at = $voucher->activated_at;
        $transaction->save();

        return $transaction;
    }

    public function reference(Voucher $voucher): string
    {
        return 'HF-VCH-'.Str::upper(str_replace('-', '', $voucher->uuid));
    }
}
