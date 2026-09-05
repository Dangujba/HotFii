<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\InvoiceSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The second write in the console, added deliberately.
 *
 * The console is read-only about money on purpose, and invoices were named in
 * ConsoleReadOnlyTest as something that must never be ticked off in a browser.
 * That held only while invoices were expected to settle themselves. They never
 * did: nothing in the codebase could mark one paid, so an organization paying by
 * bank transfer stayed suspended forever, and the console's reactivate button was
 * undone by EnforceSubscriptionGrace within the hour.
 *
 * So this exists, and is narrow: it records a payment that already happened
 * elsewhere. It cannot alter an amount, reopen a paid invoice, or raise one. It
 * demands a reference and a reason, and writes both to the audit log, because
 * "why is this invoice paid when no money arrived" has to be answerable.
 */
class InvoiceController extends Controller
{
    public function pay(Request $request, Invoice $invoice, InvoiceSettlement $settlement): RedirectResponse
    {
        $data = $request->validate([
            // The bank's transfer reference. Required: without it this is an
            // unfalsifiable claim that money arrived.
            'reference' => ['required', 'string', 'min:3', 'max:120'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        abort_if($invoice->isPaid(), 422, 'That invoice is already marked paid.');

        $settled = $settlement->settle(
            invoice: $invoice,
            method: 'manual',
            reference: $data['reference'],
            actor: $request->user(),
            reason: $data['reason'],
            ipAddress: $request->ip(),
        );

        if (! $settled) {
            return back()->with('error', 'That invoice was settled by another payment a moment ago.');
        }

        $organization = $invoice->refresh()->organization;

        return back()->with('success', sprintf(
            'Invoice %s is marked paid. %s',
            $invoice->number,
            $organization?->billing_suspended_at === null && $organization?->status !== null
                ? $organization->name.' is now '.str_replace('_', ' ', $organization->status->value).'.'
                : 'Other invoices are still overdue, so the account stays restricted.',
        ));
    }
}
