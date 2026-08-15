<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessPlan extends Model
{
    use HasPublicUuid;
    protected $fillable = ['organization_id','name','access_type','price_kobo','duration_minutes','data_limit_bytes','download_kbps','upload_kbps','simultaneous_use','validity_days','starts_on_first_use','is_active'];
    protected function casts(): array { return ['starts_on_first_use'=>'boolean','is_active'=>'boolean']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}