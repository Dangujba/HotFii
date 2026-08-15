<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningToken extends Model
{
    protected $fillable = ['network_device_id', 'created_by', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function networkDevice(): BelongsTo { return $this->belongsTo(NetworkDevice::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}