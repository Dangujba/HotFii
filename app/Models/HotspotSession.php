<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotSession extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'network_device_id', 'customer_id', 'access_plan_id',
        'source', 'radius_username', 'acct_session_id', 'external_session_id',
        'session_meta', 'mac_address', 'ip_address', 'status',
        'input_bytes', 'output_bytes', 'started_at', 'expires_at', 'stopped_at', 'terminate_cause',
    ];

    protected function casts(): array
    {
        return [
            'session_meta' => 'array',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function networkDevice(): BelongsTo { return $this->belongsTo(NetworkDevice::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function accessPlan(): BelongsTo { return $this->belongsTo(AccessPlan::class); }

    public function totalBytes(): int
    {
        return $this->input_bytes + $this->output_bytes;
    }
}