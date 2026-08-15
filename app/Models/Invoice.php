<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'number', 'billing_period', 'subtotal_kobo',
        'total_kobo', 'status', 'due_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['billing_period' => 'date', 'due_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}