<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place an invoice becomes paid.
 *
 * Settling an invoice is not just a status change: an unpaid invoice is what
 * holds an organization in Grace or Suspended, and EnforceSubscriptionGrace
 * re-applies that every hour for as long as the invoice is open. So marking one
 * paid and reconsidering the account's status have to happen together — apart,
 * a paid customer stays locked out until the next hourly run, or worse, is
 * reactivated by hand and suspended again within the hour.
 *
 * Every path in — Paystack callback, signed webhook, and the platform owner
 * recording a bank transfer — goes through settle().
 */
class InvoiceSettlement
{
    /**
     * @param  string  $method  'paystack' for an online payment, 'manual' for a transfer received out of band
     * @param  ?User  $actor  the platform admin recording a manual payment; null for machine paths
     * @return bool  true when this call was the one that settled it
     */
    public function settle(
        Invoice $invoice,
        string $method,
        ?string $reference = null,
        ?User $actor = null,
        ?string $reason = null,
        ?string $ipAddress = null,
    ): bool {
        return DB::transaction(function () use ($invoice, $method, $reference, $actor, $reason, $ipAddress): bool {
            // Locked because the Paystack callback and its webhook routinely
            // arrive at the same moment, and both would otherwise settle it.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->markPaid($method, $reference)) {
                return false;
            }

            AuditLog::create([
                'organization_id' => $invoice->organization_id,
                'user_id' => $actor?->id,
                'action' => 'invoice.paid',
                'subject_type' => Invoice::class,
                'subject_id' => $invoice->id,
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'before' => ['status' => 'open'],
                'after' => [
                    'status' => 'paid',
                    'method' => $method,
                    'reference' => $reference,
                    'total_kobo' => $invoice->total_kobo,
                ],
            ]);

            $this->restore($invoice);

            return true;
        });
    }

    /**
     * Lift a billing restriction once the account owes nothing overdue.
     *
     * Deliberately checks every other invoice rather than just this one: an
     * organization two months behind that pays only the older invoice is still
     * behind, and must stay in Grace. clearBillingSuspension() itself refuses
     * to touch an account suspended by hand from the console.
     */
    private function restore(Invoice $invoice): void
    {
        $organization = $invoice->organization;

        if ($organization === null || $organization->billing_suspended_at === null) {
            return;
        }

        if ($organization->overdueInvoices()->exists()) {
            return;
        }

        $before = $organization->status;
        $organization->clearBillingSuspension();

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => null,
            'action' => 'organization.billing-restored',
            'subject_type' => $organization::class,
            'subject_id' => $organization->id,
            'reason' => 'Invoice '.$invoice->number.' settled, and nothing else is overdue.',
            'before' => ['status' => $before->value],
            'after' => ['status' => $organization->status->value],
        ]);
    }
}
