<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Support\Bytes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessPlan extends Model
{
    use HasPublicUuid;
    protected $fillable = ['organization_id','name','access_type','price_kobo','duration_minutes','data_limit_bytes','download_kbps','upload_kbps','simultaneous_use','validity_days','starts_on_first_use','is_active'];
    protected function casts(): array { return ['starts_on_first_use'=>'boolean','is_active'=>'boolean']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    /**
     * Data cap in the unit an operator entered it in. A 500 MB plan reading
     * "0.49 GB" is no use to anyone, so anything under a gigabyte stays in
     * megabytes and larger caps carry the megabyte figure alongside.
     */
    public function dataAllowance(): ?string
    {
        return $this->data_limit_bytes ? Bytes::detailed($this->data_limit_bytes) : null;
    }
}