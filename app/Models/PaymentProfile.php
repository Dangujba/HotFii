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

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}