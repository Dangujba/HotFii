<?php

namespace App\Http\Controllers;

use App\Domain\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\Transaction;
use App\Services\Access\AllowanceService;
use App\Services\Billing\CommerceFeeCalculator;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function initialize(
        Request $request,
        NetworkDevice $device,
        CommerceFeeCalculator $fees,
        PaystackService $paystack,
    ): JsonResponse {
        $organization = $device->organization;
        abort_unless($organization->sellsAccess(), 422, 'This organization does not sell guest access.');
        // The payment profile is the only gate on collecting money. Without an
        // approved settlement subaccount there is nowhere for the money to go,
        // so this is refused in test mode too rather than paying the platform.
        abort_unless($organization->canCollectLivePayments(), 403, 'This organization has not activated its payment profile yet.');

        $data = $request->validate([
            'access_plan_uuid' => ['required', 'uuid'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'link_login' => ['nullable', 'url:http,https', 'max:500'],
            'link_orig' => ['nullable', 'url:http,https', 'max:500'],
            'mac' => ['nullable', 'string', 'max:32'],
            'ip' => ['nullable', 'ip'],
        ]);

        $plan = $organization->accessPlans()
            ->where('uuid', $data['access_plan_uuid'])
            ->where('access_type', 'paid')
            ->where('is_active', true)
            ->firstOrFail();
        abort_if($plan->price_kobo < 10000, 422, 'Online plans must cost at least ₦100.');

        $customer = Customer::firstOrCreate(
            ['organization_id' => $organization->id, 'email' => Str::lower($data['email'])],
            ['type' => 'customer', 'phone' => $data['phone'] ?? null, 'status' => 'active'],
        );

        $quote = $fees->quote($organization, $plan->price_kobo);
        $transaction = Transaction::create([
            'organization_id' => $organization->id,
            'network_device_id' => $device->id,
            'customer_id' => $customer->id,
            'access_plan_id' => $plan->id,
            'reference' => 'HF-'.Str::upper(Str::random(16)),
            'status' => PaymentStatus::Pending,
            'gross_amount_kobo' => $plan->price_kobo,
            'platform_fee_kobo' => $quote->chargeablePercentageFeeKobo(),
            'billable_sales_kobo' => $plan->price_kobo,
            'metadata' => collect($data)->only(['link_login', 'link_orig', 'mac', 'ip'])->filter()->all(),
        ]);

        try {
            $result = $paystack->initialize(
                $transaction,
                $organization,
                $data['email'],
                route('portal.payment.callback', ['device' => $device, 'transaction' => $transaction]),
            );
        } catch (RuntimeException $exception) {
            $transaction->update(['status' => PaymentStatus::Failed]);
            throw $exception;
        }

        return response()->json([
            'authorization_url' => $result['authorization_url'],
            'reference' => $transaction->reference,
        ]);
    }

    public function callback(
        Request $request,
        NetworkDevice $device,
        Transaction $transaction,
        PaystackService $paystack,
        PaymentProcessor $processor,
    ): RedirectResponse {
        $this->assertTransactionDevice($transaction, $device);

        if ($transaction->status === PaymentStatus::Pending && $request->query('reference') === $transaction->reference) {
            try {
                $data = $paystack->verify($transaction->reference);
                if (($data['status'] ?? null) === 'success') {
                    $processor->markSuccessful($transaction, $data);
                }
            } catch (RuntimeException) {
                // Signed webhook processing and scheduled recovery continue independently.
            }
        }

        return redirect()->route('portal.payment.status', ['device' => $device, 'transaction' => $transaction]);
    }

    public function status(
        NetworkDevice $device,
        Transaction $transaction,
        PaystackService $paystack,
        PaymentProcessor $processor,
        AllowanceService $allowances,
    ): View {
        $this->assertTransactionDevice($transaction, $device);

        if ($transaction->status === PaymentStatus::Pending) {
            try {
                $data = $paystack->verify($transaction->reference);
                if (($data['status'] ?? null) === 'success') {
                    $transaction = $processor->markSuccessful($transaction, $data);
                }
            } catch (RuntimeException) {
                // Keep pending until the signed webhook or scheduled recovery succeeds.
            }
        }

        $credential = $transaction->customer?->credentials()
            ->where('access_plan_id', $transaction->access_plan_id)
            ->latest()
            ->first();
        $allowance = $allowances->forCredential($credential);

        return view('portal.payment-status', compact('device', 'transaction', 'credential', 'allowance'));
    }

    public function poll(NetworkDevice $device, Transaction $transaction): JsonResponse
    {
        $this->assertTransactionDevice($transaction, $device);
        $transaction->refresh();

        return response()->json([
            'status' => $transaction->status->value,
            'redirect' => $transaction->status === PaymentStatus::Successful
                ? route('portal.payment.status', [$device, $transaction])
                : null,
        ]);
    }

    private function assertTransactionDevice(Transaction $transaction, NetworkDevice $device): void
    {
        abort_unless(
            $transaction->network_device_id === $device->id
            && $transaction->organization_id === $device->organization_id,
            404,
        );
    }
}