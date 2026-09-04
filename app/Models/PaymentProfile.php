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
     * The last four characters of a stored secret behind bullets, so the
     * settings page can show which account is on file without putting the
     * whole number back on screen where it can be read or copied.
     */
    private function maskedTail(string $attribute): ?string
    {
        try {
            $value = trim((string) $this->getAttribute($attribute));
        } catch (\Throwable) {
            // A rotated APP_KEY leaves the old ciphertext unreadable. The hint
            // is a convenience, so lose it rather than break the page.
            return null;
        }

        if ($value === '') {
            return null;
        }

        return str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}