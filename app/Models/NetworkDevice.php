<?php

namespace App\Models;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\RouterVendor;
use App\Domain\Enums\SupportLevel;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkDevice extends Model
{
    use HasPublicUuid;
    protected $fillable = ['organization_id', 'location_id', 'name', 'vendor', 'model', 'firmware_version', 'adapter', 'support_level', 'status', 'nas_identifier', 'radius_secret', 'management_address', 'management_config', 'capabilities', 'health', 'last_heartbeat_at', 'certified_at'];
    protected function casts(): array { return ['vendor'=>RouterVendor::class,'support_level'=>SupportLevel::class,'status'=>NetworkDeviceStatus::class,'radius_secret'=>'encrypted','management_config'=>'encrypted:array','capabilities'=>'array','health'=>'array','last_heartbeat_at'=>'datetime','certified_at'=>'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function tests(): HasMany { return $this->hasMany(NetworkDeviceTest::class); }
    public function sessions(): HasMany { return $this->hasMany(HotspotSession::class); }
}