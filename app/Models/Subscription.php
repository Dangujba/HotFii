<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'plan_code', 'status', 'amount_kobo',
        'current_period_starts_at', 'current_period_ends_at', 'grace_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}