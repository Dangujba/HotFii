<?php

namespace App\Models;

use App\Domain\Enums\PaymentStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'network_device_id', 'customer_id', 'access_plan_id',
        'reference', 'provider', 'channel', 'status', 'gross_amount_kobo',
        'gateway_fee_kobo', 'platform_fee_kobo', 'billable_sales_kobo',
        'provider_response', 'metadata', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'provider_response' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function networkDevice(): BelongsTo { return $this->belongsTo(NetworkDevice::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function accessPlan(): BelongsTo { return $this->belongsTo(AccessPlan::class); }
}