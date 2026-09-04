<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\Payments\LivePaymentActivator;
use App\Services\Payments\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly LivePaymentActivator $activator,
    ) {}

    public function index(Organization $organization): View
    {
        return view('operator.settings', [
            'paymentProfile' => $organization->paymentProfile,
            'banks' => $organization->sellsAccess() ? $this->paystack->banks() : [],
            'auditLogs' => AuditLog::where('organization_id', $organization->id)
                ->with('user')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'portal_name' => ['nullable', 'string', 'max:80'],
            'portal_primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $before = $organization->only(['name', 'timezone', 'branding']);
        $organization->update([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'branding' => [
                'portal_name' => $data['portal_name'] ?? $data['name'],
                'primary_color' => $data['portal_primary_color'] ?? '#f4610a',
            ],
        ]);

        $this->audit($request, $organization, 'organization.settings.updated', $before, $organization->only(['name', 'timezone', 'branding']));

        return back()->with('success', 'Organization settings updated.');
    }

    public function submitPaymentProfile(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->sellsAccess(), 422, 'Internal organizations do not need live-payment approval.');

        $banks = $this->paystack->banks();

        $rules = [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'digits_between:10,16'],
            'identity_type' => ['required', 'in:nin,bvn,cac'],
            'identity_number' => ['required', 'string', 'min:8', 'max:32'],
        ];

        // With the bank list available we capture the settlement bank code,
        // which is what Paystack needs. Without it, fall back to a typed
        // bank name and let the platform review the profile by hand.
        if ($banks !== []) {
            $rules['bank_code'] = ['required', 'string', Rule::in(array_column($banks, 'code'))];
        } else {
            $rules['bank_name'] = ['required', 'string', 'max:100'];
        }

        $data = $request->validate($rules);

        $bankCode = $data['bank_code'] ?? null;
        $bankName = $bankCode ? $this->paystack->bankName($bankCode) : null;

        $profile = $organization->paymentProfile()->updateOrCreate(
            [],
            [
                'business_name' => $data['business_name'],
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'bank_name' => $bankName ?? $data['bank_name'] ?? '',
                'bank_code' => $bankCode,
                'account_name' => $data['account_name'],
                'resolved_account_name' => null,
                'account_number_cipher' => $data['account_number'],
                'identity_type' => $data['identity_type'],
                'identity_number_cipher' => $data['identity_number'],
                'status' => 'submitted',
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'auto_approved_at' => null,
                'review_notes' => null,
            ],
        );

        // Submitting bank details must not take a working organization
        // offline. Status stays as it is; the profile carries the review
        // state, and only PaymentRejected accounts are lifted back to Live.
        if ($organization->status === OrganizationStatus::PaymentRejected) {
            $organization->update(['status' => OrganizationStatus::Live]);
        }

        $this->audit($request, $organization, 'payment-profile.submitted', [], ['profile_id' => $profile->id]);

        $result = $this->activator->attempt($organization, $profile, (string) $request->user()->email);

        if ($result['approved']) {
            $this->audit($request, $organization, 'payment-profile.auto-approved', [], [
                'profile_id' => $profile->id,
                'verification' => $result['verification'],
                'resolved_account_name' => $result['resolved_account_name'],
            ]);

            return back()->with('success', $result['verification'] === 'skipped-test-mode'
                ? 'Live payments are enabled. Paystack is in test mode, so the settlement account was not checked against the bank.'
                : 'Live payments are enabled. Paystack confirmed your settlement account and your subaccount is ready.');
        }

        $profile->update(['review_notes' => $result['reason']]);
        $this->audit($request, $organization, 'payment-profile.auto-approval-declined', [], [
            'profile_id' => $profile->id,
            'reason' => $result['reason'],
        ]);

        return back()->with('success', 'Payment details submitted. '.$result['reason'].' A HotFii reviewer will finish the check. Everything else on your account keeps working, and you can still sell with vouchers and cash.');
    }

    private function audit(Request $request, Organization $organization, string $action, array $before, array $after): void
    {
        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Organization::class,
            'subject_id' => $organization->id,
            'ip_address' => $request->ip(),
            'before' => $before,
            'after' => $after,
        ]);
    }
}