<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.settings', [
            'paymentProfile' => $organization->paymentProfile,
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
                'primary_color' => $data['portal_primary_color'] ?? '#146c43',
            ],
        ]);

        $this->audit($request, $organization, 'organization.settings.updated', $before, $organization->only(['name', 'timezone', 'branding']));

        return back()->with('success', 'Organization settings updated.');
    }

    public function submitPaymentProfile(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->sellsAccess(), 422, 'Internal organizations do not need live-payment approval.');

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'digits_between:10,16'],
            'identity_type' => ['required', 'in:nin,bvn,cac'],
            'identity_number' => ['required', 'string', 'min:8', 'max:32'],
        ]);

        $profile = $organization->paymentProfile()->updateOrCreate(
            [],
            [
                'business_name' => $data['business_name'],
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'bank_name' => $data['bank_name'],
                'account_name' => $data['account_name'],
                'account_number_cipher' => $data['account_number'],
                'identity_type' => $data['identity_type'],
                'identity_number_cipher' => $data['identity_number'],
                'status' => 'submitted',
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'review_notes' => null,
            ],
        );

        $organization->update(['status' => OrganizationStatus::PaymentReview]);
        $this->audit($request, $organization, 'payment-profile.submitted', [], ['profile_id' => $profile->id]);

        return back()->with('success', 'Payment details submitted for review. Sandbox testing remains available.');
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