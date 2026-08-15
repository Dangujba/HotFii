<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkDeviceTest extends Model
{
    protected $fillable = ['network_device_id','run_uuid','test_key','status','message','details','checked_at'];
    protected function casts(): array { return ['details'=>'array','checked_at'=>'datetime']; }
    public function networkDevice(): BelongsTo { return $this->belongsTo(NetworkDevice::class); }
}