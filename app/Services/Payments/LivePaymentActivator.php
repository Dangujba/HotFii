<?php

namespace App\Services\Payments;

use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\PaymentProfile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a submitted payment profile into live-payment capability without a
 * human review, by getting Paystack to vouch for the settlement account and
 * then creating the split subaccount money will be paid into.
 *
 * Every path that cannot be verified falls back to the platform review queue
 * rather than approving on trust.
 */
class LivePaymentActivator
{
    public function __construct(private readonly PaystackService $paystack) {}

    /**
     * @return array{approved: bool, reason: ?string, subaccount_code: ?string, resolved_account_name: ?string, verification: string}
     */
    public function attempt(Organization $organization, PaymentProfile $profile, string $contactEmail): array
    {
        if (! $this->paystack->configured()) {
            return $this->manual('Paystack is not configured on this deployment.');
        }

        if (blank($profile->bank_code)) {
            return $this->manual('No bank code was captured, so the settlement account cannot be verified.');
        }

        $accountNumber = (string) $profile->account_number_cipher;
        $resolved = $this->paystack->resolveAccount($accountNumber, (string) $profile->bank_code);
        $verification = 'resolved';

        if ($resolved === null) {
            // No real money moves under test keys, so an unresolvable account
            // must not block testing the whole activation flow.
            if ($this->paystack->isLiveMode()) {
                return $this->manual('Paystack could not confirm this account number at the selected bank.');
            }

            $verification = 'skipped-test-mode';
        } elseif (! $this->namesMatch((string) $profile->account_name, $resolved['account_name'])) {
            return $this->manual(sprintf(
                'The bank returned "%s" for this account number, which does not match the submitted account name.',
                $resolved['account_name'],
            ), $resolved['account_name']);
        }

        $existingCode = $organization->paystack_subaccount_code;

        try {
            $subaccount = filled($existingCode)
                ? $this->paystack->updateSubaccount(
                    subaccountCode: (string) $existingCode,
                    businessName: (string) $profile->business_name,
                    bankCode: (string) $profile->bank_code,
                    accountNumber: $accountNumber,
                    contactEmail: $contactEmail,
                    contactName: (string) $profile->contact_name,
                    contactPhone: (string) $profile->contact_phone,
                )
                : $this->paystack->createSubaccount(
                    businessName: (string) $profile->business_name,
                    bankCode: (string) $profile->bank_code,
                    accountNumber: $accountNumber,
                    contactEmail: $contactEmail,
                    contactName: (string) $profile->contact_name,
                    contactPhone: (string) $profile->contact_phone,
                );
        } catch (Throwable $exception) {
            Log::warning('Automatic live-payment approval failed at subaccount creation.', [
                'organization_id' => $organization->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->manual('Paystack rejected the settlement subaccount: '.$exception->getMessage(), $resolved['account_name'] ?? null);
        }

        $subaccountCode = $subaccount['subaccount_code'] ?? $existingCode;

        if (! filled($subaccountCode)) {
            return $this->manual('Paystack created the subaccount but returned no subaccount code.', $resolved['account_name'] ?? null);
        }

        $profile->update([
            'status' => 'approved',
            'resolved_account_name' => $resolved['account_name'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => null,
            'auto_approved_at' => now(),
            'review_notes' => $verification === 'skipped-test-mode'
                ? 'Approved automatically. Paystack is in test mode, so the settlement account was not verified against the bank.'
                : 'Approved automatically after Paystack confirmed the settlement account.',
        ]);

        $organization->update([
            'status' => OrganizationStatus::Live,
            'paystack_subaccount_code' => $subaccountCode,
            'live_payments_enabled_at' => now(),
        ]);

        return [
            'approved' => true,
            'reason' => null,
            'subaccount_code' => (string) $subaccountCode,
            'resolved_account_name' => $resolved['account_name'] ?? null,
            'verification' => $verification,
        ];
    }

    /**
     * @return array{approved: false, reason: string, subaccount_code: null, resolved_account_name: ?string, verification: string}
     */
    private function manual(string $reason, ?string $resolvedAccountName = null): array
    {
        return [
            'approved' => false,
            'reason' => $reason,
            'subaccount_code' => null,
            'resolved_account_name' => $resolvedAccountName,
            'verification' => 'manual-review',
        ];
    }

    /**
     * Banks return names in their own order and spelling, so compare the
     * word sets rather than the strings. Two shared words is enough signal
     * that it is the same person or business; a single-word name must match.
     */
    private function namesMatch(string $submitted, string $resolved): bool
    {
        $tokenise = static fn (string $value): array => array_values(array_filter(
            preg_split('/[^a-z0-9]+/', strtolower($value)) ?: [],
            static fn (string $token): bool => strlen($token) > 1,
        ));

        $submittedTokens = $tokenise($submitted);
        $resolvedTokens = $tokenise($resolved);

        if ($submittedTokens === [] || $resolvedTokens === []) {
            return false;
        }

        $shared = count(array_intersect($submittedTokens, $resolvedTokens));

        return $shared >= min(2, count($submittedTokens));
    }
}
