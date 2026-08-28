<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\Payments\LivePaymentActivator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReviewController extends Controller
{
    public function __construct(private readonly LivePaymentActivator $activator) {}

    public function approve(Request $request, Organization $organization): RedirectResponse
    {
        $profile = $organization->paymentProfile;
        abort_unless($profile && $profile->status === 'submitted', 422, 'There is no submitted payment profile.');

        $data = $request->validate([
            'paystack_subaccount_code' => ['nullable', 'string', 'max:100'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // No code typed means "let HotFii open the subaccount", which is the
        // same path automatic approval takes.
        if (blank($data['paystack_subaccount_code'] ?? null)) {
            $ownerEmail = $organization->users()->wherePivot('role', 'owner')->value('users.email');
            $result = $this->activator->attempt($organization, $profile, (string) ($ownerEmail ?: $request->user()->email));

            if (! $result['approved']) {
                return back()->withErrors([
                    'paystack_subaccount_code' => $result['reason'].' Create the subaccount in Paystack and paste its code here.',
                ]);
            }

            $profile->update([
                'reviewed_by' => $request->user()->id,
                'review_notes' => $data['review_notes'] ?? $profile->review_notes,
            ]);

            $this->audit($request, $organization, 'payment-profile.approved', $data['review_notes'] ?? null);

            return back()->with('success', 'Live payments approved and the settlement subaccount was created for them.');
        }

        $profile->update([
            'status' => 'approved',
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'auto_approved_at' => null,
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
            'auto_approved_at' => null,
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