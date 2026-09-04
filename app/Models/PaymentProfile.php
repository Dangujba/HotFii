<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProfile extends Model
{
    protected $fillable = [
        'organization_id', 'business_name', 'contact_name', 'contact_phone', 'bank_name', 'bank_code',
        'account_name', 'resolved_account_name', 'account_number_cipher', 'identity_type', 'identity_number_cipher',
        'status', 'review_notes', 'reviewed_by', 'submitted_at', 'reviewed_at', 'auto_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'account_number_cipher' => 'encrypted',
            'identity_number_cipher' => 'encrypted',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'auto_approved_at' => 'datetime',
        ];
    }

    public function wasAutoApproved(): bool
    {
        return $this->auto_approved_at !== null;
    }

    /** Masked account number, for confirming what is on file. */
    public function accountNumberHint(): ?string
    {
        return $this->maskedTail('account_number_cipher');
    }

    /** Masked identity number, for confirming what is on file. */
    public function identityNumberHint(): ?string
    {
        return $this->maskedTail('identity_number_cipher');
    }

    /**
     * The full account number, for the one screen that needs it: checking a
     * submitted profile against the bank is the reviewer's whole job.
     */
    public function accountNumberForReview(): ?string
    {
        return $this->plaintext('account_number_cipher');
    }

    /** The full identity number, for the same reason. */
    public function identityNumberForReview(): ?string
    {
        return $this->plaintext('identity_number_cipher');
    }

    /**
     * The last four characters of a stored secret behind bullets, so the
     * settings page can show which account is on file without putting the
     * whole number back on screen where it can be read or copied.
     */
    private function maskedTail(string $attribute): ?string
    {
        $value = $this->plaintext($attribute);

        if ($value === null) {
            return null;
        }

        return str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }

    /**
     * A decrypted field, or null.
     *
     * The encrypted cast throws once APP_KEY has been rotated, and every caller
     * here is displaying a field on a page full of other fields — losing one of
     * them beats taking the page down.
     */
    private function plaintext(string $attribute): ?string
    {
        try {
            $value = trim((string) $this->getAttribute($attribute));
        } catch (\Throwable) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}