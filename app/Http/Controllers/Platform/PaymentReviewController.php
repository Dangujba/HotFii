<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReviewController extends Controller
{
    public function approve(Request $request, Organization $organization): RedirectResponse
    {
        $profile = $organization->paymentProfile;
        abort_unless($profile && $profile->status === 'submitted', 422, 'There is no submitted payment profile.');

        $data = $request->validate([
            'paystack_subaccount_code' => ['required', 'string', 'max:100'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile->update([
            'status' => 'approved',
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $organization->update([
            'status' => OrganizationStatus::Live,
            'paystack_subaccount_code' => $data['paystack_subaccount_code'],
            'live_payments_enabled_at' => now(),
        ]);

        $this->audit($request, $organization, 'payment-profile.approved', $data['review_notes'] ?? null);
        return back()->with('success', 'Live payments approved. The trial starts on the first successful live activation.');
    }

    public function reject(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['review_notes' => ['required', 'string', 'max:2000']]);
        abort_unless($organization->paymentProfile, 422, 'There is no payment profile.');

        $organization->paymentProfile->update([
            'status' => 'rejected',
            'review_notes' => $data['review_notes'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $organization->update([
            'status' => OrganizationStatus::PaymentRejected,
            'live_payments_enabled_at' => null,
        ]);

        $this->audit($request, $organization, 'payment-profile.rejected', $data['review_notes']);
        return back()->with('success', 'Payment profile rejected with review notes.');
    }

    private function audit(Request $request, Organization $organization, string $action, ?string $reason): void
    {
        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Organization::class,
            'subject_id' => $organization->id,
            'ip_address' => $request->ip(),
            'reason' => $reason,
        ]);
    }
}