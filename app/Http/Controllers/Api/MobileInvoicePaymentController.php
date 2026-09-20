<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\Billing\InvoiceSettlement;
use App\Services\Payments\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class MobileInvoicePaymentController extends Controller
{
    public function initialize(Request $request, Organization $organization, Invoice $invoice, PaystackService $paystack): JsonResponse
    {
        abort_unless($invoice->organization_id === $organization->id, 404);
        abort_unless(in_array($request->user()->roleFor($organization), ['owner', 'manager'], true), 403);
        abort_if($invoice->isPaid(), 422, 'That invoice is already paid.');
        abort_unless($paystack->configured(), 503, 'Online invoice payment is unavailable. Please pay by transfer.');

        $reference = 'HF-INVPAY-'.Str::upper(Str::random(14));
        $invoice->forceFill(['payment_reference' => $reference])->save();
        $callback = URL::temporarySignedRoute(
            'mobile.invoice-payments.callback',
            now()->addHours(3),
            ['invoice' => $invoice],
        );

        try {
            $result = $paystack->initializeInvoice(
                $invoice,
                $reference,
                (string) $request->user()->email,
                $callback,
            );
        } catch (RuntimeException $exception) {
            abort(502, 'Paystack could not start that payment: '.$exception->getMessage());
        }

        return response()->json(['data' => [
            'authorization_url' => $result['authorization_url'],
            'reference' => $reference,
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function callback(Request $request, Invoice $invoice, PaystackService $paystack, InvoiceSettlement $settlement): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['reference', 'trxref']), 403);
        $reference = (string) $request->query('reference', $invoice->payment_reference);

        if (! $invoice->isPaid() && $reference !== '' && $reference === $invoice->payment_reference) {
            try {
                $data = $paystack->verify($reference);
                if (($data['status'] ?? null) === 'success' && (int) ($data['amount'] ?? 0) >= $invoice->total_kobo) {
                    $settlement->settle($invoice, 'paystack', $reference);
                }
            } catch (RuntimeException) {
                // The signed webhook settles this independently.
            }
        }

        $status = $invoice->refresh()->isPaid() ? 'paid' : 'pending';

        return redirect()->away('hotfii://invoice-payment?status='.$status.'&invoice='.urlencode($invoice->uuid));
    }
}
