<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccessGroup extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'organization_id', 'name', 'schedule', 'data_limit_bytes',
        'download_kbps', 'upload_kbps', 'device_limit', 'is_active',
    ];

    protected function casts(): array
    {
        return ['schedule' => 'array', 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function customers(): BelongsToMany { return $this->belongsToMany(Customer::class); }
}