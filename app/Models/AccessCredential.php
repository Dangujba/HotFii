<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessCredential extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'customer_id', 'access_plan_id', 'voucher_id', 'username',
        'password_cipher', 'status', 'starts_at', 'expires_at', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'password_cipher' => 'encrypted',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function accessPlan(): BelongsTo { return $this->belongsTo(AccessPlan::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
}