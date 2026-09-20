<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherDeviceBinding extends Model
{
    protected $fillable = [
        'organization_id',
        'network_device_id',
        'voucher_id',
        'access_credential_id',
        'mac_address',
        'status',
        'first_seen_at',
        'last_seen_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(
            AccessCredential::class,
            'access_credential_id'
        );
    }
}
