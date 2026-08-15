<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'action', 'subject_type', 'subject_id',
        'ip_address', 'reason', 'before', 'after',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}