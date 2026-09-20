<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDevice extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'user_id', 'platform', 'name', 'device_identifier_hash', 'push_token',
        'push_token_hash', 'app_version', 'os_version', 'last_seen_at',
    ];

    protected $hidden = ['push_token', 'push_token_hash', 'device_identifier_hash'];

    protected function casts(): array
    {
        return [
            'push_token' => 'encrypted',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
