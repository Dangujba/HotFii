<?php

namespace App\Models;

use App\Domain\Enums\VoucherStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voucher extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'voucher_batch_id', 'customer_id', 'code_lookup',
        'code_cipher', 'code_last_four', 'status', 'price_snapshot_kobo',
        'is_complimentary', 'sold_at', 'activated_at', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'code_cipher' => 'encrypted',
            'status' => VoucherStatus::class,
            'is_complimentary' => 'boolean',
            'sold_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function batch(): BelongsTo { return $this->belongsTo(VoucherBatch::class, 'voucher_batch_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function credential(): HasOne { return $this->hasOne(AccessCredential::class); }
}