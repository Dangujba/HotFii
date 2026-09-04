<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Billing\CommerceFeeCalculator;
use App\Services\Billing\TrialManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.sales', [
            'transactions' => $organization->transactions()->with('customer', 'accessPlan')->latest()->paginate(25),
            'voucherSales' => $organization->vouchers()->with('batch.accessPlan')->whereNotNull('sold_at')->latest('sold_at')->limit(25)->get(),
            'plans' => $organization->accessPlans()->where('access_type', 'paid')->where('is_active', true)->orderBy('name')->get(),
            'devices' => $organization->networkDevices()->where('status', 'online')->orderBy('name')->get(),
            'totals' => [
                'online' => $organization->transactions()->where('channel', 'online')->where('status', 'successful')->sum('gross_amount_kobo'),
                'voucher' => $organization->vouchers()->whereNotNull('activated_at')->where('is_complimentary', false)->sum('price_snapshot_kobo'),
                'cash' => $organization->transactions()->where('channel', 'cash')->where('status', 'successful')->sum('gross_amount_kobo'),
            ],
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        CommerceFeeCalculator $fees,
        TrialManager $trials,
        PaymentProcessor $processor,
    ): RedirectResponse {
        abort_unless($organization->sellsAccess(), 422);
        abort_if(in_array($organization->status, [OrganizationStatus::Suspended, OrganizationStatus::Grace], true), 422, 'New paid activations are unavailable while billing is overdue.');

        $data = $request->validate([
            'access_plan_id' => ['required', 'integer'],
            'network_device_id' => ['required', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $plan = $organization->accessPlans()->where('access_type', 'paid')->where('is_active', true)->findOrFail($data['access_plan_id']);
        $device = $organization->networkDevices()->where('status', 'online')->findOrFail($data['network_device_id']);

        // The trial clock starts on the first real activation, whatever the
        // account status is, so a never-sold organization is not billed.
        if (! $organization->trial_started_at) {
            $organization = $trials->start($organization);
        }

        $customer = ($data['phone'] ?? null) || ($data['customer_name'] ?? null)
            ? Customer::firstOrCreate(
                ['organization_id' => $organization->id, 'phone' => $data['phone'] ?? null],
                ['name' => $data['customer_name'] ?? null, 'type' => 'customer', 'status' => 'active'],
            )
            : $organization->customers()->create(['type' => 'customer', 'status' => 'active']);

        $quote = $fees->quote($organization, $plan->price_kobo);
        $transaction = Transaction::create([
            'organization_id' => $organization->id,
            'network_device_id' => $device->id,
            'customer_id' => $customer->id,
            'access_plan_id' => $plan->id,
            'reference' => 'HF-CASH-'.Str::upper(Str::random(12)),
            'provider' => 'manual',
            'channel' => 'cash',
            'status' => PaymentStatus::Pending,
            'gross_amount_kobo' => $plan->price_kobo,
            'platform_fee_kobo' => $quote->chargeablePercentageFeeKobo(),
            'billable_sales_kobo' => $plan->price_kobo,
        ]);

        $processor->markSuccessful($transaction, ['amount' => $plan->price_kobo, 'fees' => 0, 'channel' => 'cash']);
        $credential = $customer->credentials()->latest()->first();

        return back()->with('success', 'Cash sale recorded and RADIUS access activated.')
            ->with('issued_credential', ['username' => $credential?->username, 'password' => $credential?->password_cipher]);
    }
}