<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\Billing\InvoiceSettlement;
use App\Services\Payments\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lets an organization pay its HotFii invoice.
 *
 * Until this existed the money loop had no end: GenerateMonthlyInvoices raised
 * invoices, EnforceSubscriptionGrace suspended accounts over them, and there was
 * no route, button or code path anywhere that could settle one. An organization
 * could be suspended for a bill it had no way to pay.
 *
 * The charge goes to the platform's own Paystack account with no split — see
 * PaystackService::initializeInvoice().
 */
class InvoicePaymentController extends Controller
{
    public function initialize(
        Request $request,
        Organization $organization,
        Invoice $invoice,
        PaystackService $paystack,
    ): RedirectResponse {
        $this->assertInvoiceBelongsTo($invoice, $organization);

        abort_if($invoice->isPaid(), 422, 'That invoice is already paid.');
        abort_unless($paystack->configured(), 503, 'Online invoice payment is unavailable. Please pay by transfer.');

        // A fresh reference each attempt: Paystack rejects a reference it has
        // already seen, so an abandoned checkout would otherwise lock the
        // invoice out of every future attempt. The newest one is what the
        // webhook and callback match on.
        $reference = 'HF-INVPAY-'.Str::upper(Str::random(14));
        $invoice->forceFill(['payment_reference' => $reference])->save();

        try {
            $result = $paystack->initializeInvoice(
                $invoice,
                $reference,
                (string) $request->user()->email,
                route('finance.invoices.callback', ['invoice' => $invoice]),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', 'Paystack could not start that payment: '.$exception->getMessage());
        }

        return redirect()->away($result['authorization_url']);
    }

    /**
     * Where Paystack returns the payer. Verifies rather than trusting the
     * redirect, and stays harmless if the signed webhook settled it first —
     * InvoiceSettlement::settle() is a no-op on an invoice already paid.
     */
    public function callback(
        Request $request,
        Organization $organization,
        Invoice $invoice,
        PaystackService $paystack,
        InvoiceSettlement $settlement,
    ): RedirectResponse {
        $this->assertInvoiceBelongsTo($invoice, $organization);

        $reference = (string) $request->query('reference', $invoice->payment_reference);

        if (! $invoice->isPaid() && $reference !== '' && $reference === $invoice->payment_reference) {
            try {
                $data = $paystack->verify($reference);

                if (($data['status'] ?? null) === 'success' && (int) ($data['amount'] ?? 0) >= $invoice->total_kobo) {
                    $settlement->settle($invoice, 'paystack', $reference);
                }
            } catch (RuntimeException) {
                // The signed webhook settles it independently; leave it open.
            }
        }

        return redirect()->route('finance.index')->with(
            $invoice->refresh()->isPaid() ? 'success' : 'error',
            $invoice->isPaid()
                ? 'Invoice '.$invoice->number.' is paid. Any billing restriction on your account has been lifted.'
                : 'That payment has not been confirmed yet. If you were charged it will clear within a few minutes.',
        );
    }

    private function assertInvoiceBelongsTo(Invoice $invoice, Organization $organization): void
    {
        abort_unless($invoice->organization_id === $organization->id, 404);
    }
}
