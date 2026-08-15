<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasPublicUuid;
    protected $fillable = ['organization_id', 'name', 'address', 'latitude', 'longitude', 'timezone', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function networkDevices(): HasMany { return $this->hasMany(NetworkDevice::class); }
}