<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PaymentProfile;
use App\Services\Payments\LivePaymentActivator;
use App\Services\Payments\PaystackService;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileOrganizationSettingsController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly LivePaymentActivator $activator,
    ) {}

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'audit_page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $role = $request->user()->roleFor($organization);
        $canManageOrganization = in_array($role, ['owner', 'manager'], true);
        $canManagePaymentProfile = $role === 'owner' && $organization->sellsAccess();
        $logs = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->with('user:id,uuid,name,email')
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20), ['*'], 'audit_page');

        return response()->json(['data' => [
            'organization' => $this->organization($organization),
            'payment_profile' => $canManagePaymentProfile
                ? $this->paymentProfile($organization->paymentProfile)
                : null,
            'audit_logs' => collect($logs->items())->map(fn (AuditLog $log) => [
                'id' => (string) $log->id,
                'action' => $log->action,
                'actor_name' => $log->user?->name ?? 'System',
                'reason' => $log->reason,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'audit_pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'options' => [
                'timezones' => DateTimeZone::listIdentifiers(),
                'banks' => $canManagePaymentProfile ? $this->paystack->banks() : [],
                'identity_types' => [
                    ['value' => 'nin', 'label' => 'NIN'],
                    ['value' => 'bvn', 'label' => 'BVN'],
                    ['value' => 'cac', 'label' => 'CAC'],
                ],
            ],
            'permissions' => [
                'can_manage_organization' => $canManageOrganization,
                'can_manage_payment_profile' => $canManagePaymentProfile,
            ],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        abort_unless(
            in_array($request->user()->roleFor($organization), ['owner', 'manager'], true),
            403,
            'You cannot change this organization\'s settings.',
        );
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

        return response()->json([
            'data' => ['organization' => $this->organization($organization->refresh())],
            'message' => 'Organization settings updated.',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function submitPaymentProfile(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()->roleFor($organization) === 'owner', 403, 'Only an owner can submit payment details.');
        abort_unless($organization->sellsAccess(), 422, 'Internal organizations do not need live-payment approval.');

        $banks = $this->paystack->banks();
        $existing = $organization->paymentProfile;
        $keepable = $existing ? ['nullable'] : ['required'];
        $rules = [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => [...$keepable, 'digits_between:10,16'],
            'identity_type' => ['required', 'in:nin,bvn,cac'],
            'identity_number' => [...$keepable, 'string', 'min:8', 'max:32'],
        ];
        if ($banks !== []) {
            $rules['bank_code'] = ['required', 'string', Rule::in(array_column($banks, 'code'))];
        } else {
            $rules['bank_name'] = ['required', 'string', 'max:100'];
        }
        $data = $request->validate($rules);
        $bankCode = $data['bank_code'] ?? null;
        $attributes = [
            'business_name' => $data['business_name'],
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'bank_name' => $bankCode ? ($this->paystack->bankName($bankCode) ?? '') : $data['bank_name'],
            'bank_code' => $bankCode,
            'account_name' => $data['account_name'],
            'resolved_account_name' => null,
            'identity_type' => $data['identity_type'],
            'status' => 'submitted',
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'auto_approved_at' => null,
            'review_notes' => null,
        ];
        if (filled($data['account_number'] ?? null)) {
            $attributes['account_number_cipher'] = $data['account_number'];
        }
        if (filled($data['identity_number'] ?? null)) {
            $attributes['identity_number_cipher'] = $data['identity_number'];
        }
        $profile = $organization->paymentProfile()->updateOrCreate([], $attributes);
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
            $message = $result['verification'] === 'skipped-test-mode'
                ? 'Live payments are enabled. Paystack is in test mode, so the bank account was not checked.'
                : 'Live payments are enabled and the settlement account is ready.';
        } else {
            $profile->update(['review_notes' => $result['reason']]);
            $this->audit($request, $organization, 'payment-profile.auto-approval-declined', [], [
                'profile_id' => $profile->id,
                'reason' => $result['reason'],
            ]);
            $message = 'Payment details submitted. '.$result['reason'].' A HotFii reviewer will finish the check.';
        }

        return response()->json([
            'data' => ['payment_profile' => $this->paymentProfile($profile->refresh())],
            'message' => $message,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function organization(Organization $organization): array
    {
        $branding = $organization->branding ?? [];

        return [
            'id' => $organization->uuid,
            'name' => $organization->name,
            'timezone' => $organization->timezone,
            'mode' => $organization->mode->value,
            'status' => $organization->status->value,
            'portal_name' => $branding['portal_name'] ?? $organization->name,
            'portal_primary_color' => $branding['primary_color'] ?? '#f4610a',
            'live_payments_enabled' => $organization->canCollectLivePayments(),
        ];
    }

    private function paymentProfile(?PaymentProfile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'business_name' => $profile->business_name,
            'contact_name' => $profile->contact_name,
            'contact_phone' => $profile->contact_phone,
            'bank_name' => $profile->bank_name,
            'bank_code' => $profile->bank_code,
            'account_name' => $profile->account_name,
            'account_number_hint' => $profile->accountNumberHint(),
            'identity_type' => $profile->identity_type,
            'identity_number_hint' => $profile->identityNumberHint(),
            'status' => $profile->status,
            'review_notes' => $profile->review_notes,
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
        ];
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
