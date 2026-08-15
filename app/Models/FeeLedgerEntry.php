<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeLedgerEntry extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'source_type', 'source_id', 'billing_period',
        'billable_sales_kobo', 'fee_amount_kobo', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['billing_period' => 'date', 'metadata' => 'array'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}