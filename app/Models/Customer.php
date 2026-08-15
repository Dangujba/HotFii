<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'type', 'name', 'email', 'phone', 'status',
        'expires_at', 'last_authenticated_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_authenticated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function accessGroups(): BelongsToMany { return $this->belongsToMany(AccessGroup::class); }
    public function sessions(): HasMany { return $this->hasMany(HotspotSession::class); }
    public function credentials(): HasMany { return $this->hasMany(AccessCredential::class); }
    public function currentCredential(): HasOne { return $this->hasOne(AccessCredential::class)->latestOfMany(); }
}